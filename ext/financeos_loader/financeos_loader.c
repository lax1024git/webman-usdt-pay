#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "php_financeos_loader.h"

#include <openssl/evp.h>
#include <openssl/sha.h>
#include <string.h>

ZEND_BEGIN_MODULE_GLOBALS(financeos_loader)
    char *key;
ZEND_END_MODULE_GLOBALS(financeos_loader)

ZEND_DECLARE_MODULE_GLOBALS(financeos_loader)

#ifdef ZTS
#define FOS_G(v) TSRMG(financeos_loader_globals_id, zend_financeos_loader_globals *, v)
#else
#define FOS_G(v) (financeos_loader_globals.v)
#endif

static zend_op_array *(*fos_original_compile_file)(zend_file_handle *file_handle, int type);

static void php_financeos_loader_init_globals(zend_financeos_loader_globals *globals)
{
    globals->key = NULL;
}

PHP_INI_BEGIN()
    STD_PHP_INI_ENTRY("financeos_loader.key", "", PHP_INI_SYSTEM, OnUpdateString, key, zend_financeos_loader_globals, financeos_loader_globals)
PHP_INI_END()

static int fos_aes_decrypt(
    const unsigned char *key32,
    const unsigned char *iv,
    const unsigned char *cipher,
    size_t cipher_len,
    unsigned char **plain,
    size_t *plain_len
) {
    EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
    if (!ctx) {
        return 0;
    }
    unsigned char *out = emalloc(cipher_len + EVP_MAX_BLOCK_LENGTH);
    int len = 0, total = 0;
    if (EVP_DecryptInit_ex(ctx, EVP_aes_256_cbc(), NULL, key32, iv) != 1
        || EVP_DecryptUpdate(ctx, out, &len, cipher, (int)cipher_len) != 1) {
        efree(out);
        EVP_CIPHER_CTX_free(ctx);
        return 0;
    }
    total = len;
    if (EVP_DecryptFinal_ex(ctx, out + total, &len) != 1) {
        efree(out);
        EVP_CIPHER_CTX_free(ctx);
        return 0;
    }
    total += len;
    EVP_CIPHER_CTX_free(ctx);
    *plain = out;
    *plain_len = (size_t)total;
    return 1;
}

static int fos_read_file(const char *path, char **buf, size_t *len)
{
    php_stream *stream = php_stream_open_wrapper((char *)path, "rb", REPORT_ERRORS, NULL);
    if (!stream) {
        return 0;
    }
    zend_string *zstr = php_stream_copy_to_mem(stream, PHP_STREAM_COPY_ALL, 0);
    php_stream_close(stream);
    if (!zstr) {
        return 0;
    }
    *len = ZSTR_LEN(zstr);
    *buf = estrndup(ZSTR_VAL(zstr), ZSTR_LEN(zstr));
    zend_string_release(zstr);
    return 1;
}

static zend_op_array *fos_compile_file(zend_file_handle *file_handle, int type)
{
    const char *filename = NULL;
    if (file_handle && file_handle->filename) {
        filename = ZSTR_VAL(file_handle->filename);
    }
    if (!filename || !FOS_G(key) || FOS_G(key)[0] == '\0') {
        return fos_original_compile_file(file_handle, type);
    }

    char *buf = NULL;
    size_t len = 0;
    if (!fos_read_file(filename, &buf, &len) || len < (FOS_MAGIC_LEN + 16 + 1)) {
        if (buf) {
            efree(buf);
        }
        return fos_original_compile_file(file_handle, type);
    }

    if (memcmp(buf, FOS_MAGIC, FOS_MAGIC_LEN) != 0) {
        efree(buf);
        return fos_original_compile_file(file_handle, type);
    }

    unsigned char key32[SHA256_DIGEST_LENGTH];
    SHA256((const unsigned char *)FOS_G(key), strlen(FOS_G(key)), key32);

    unsigned char *plain = NULL;
    size_t plain_len = 0;
    if (!fos_aes_decrypt(
            key32,
            (unsigned char *)buf + FOS_MAGIC_LEN,
            (unsigned char *)buf + FOS_MAGIC_LEN + 16,
            len - FOS_MAGIC_LEN - 16,
            &plain,
            &plain_len
        )) {
        efree(buf);
        php_error_docref(NULL, E_COMPILE_ERROR, "financeos_loader: decrypt failed for %s", filename);
        return NULL;
    }
    efree(buf);

    zend_string *code = zend_string_init((char *)plain, plain_len, 0);
    efree(plain);

#if PHP_VERSION_ID >= 80200
    /* 解密结果是完整 PHP 文件（含 <?php），必须用 AT_OPEN_TAG；AFTER_OPEN_TAG 会报 unexpected "<" */
    zend_op_array *op_array = zend_compile_string(code, filename, ZEND_COMPILE_POSITION_AT_OPEN_TAG);
#else
    zend_op_array *op_array = zend_compile_string(code, filename);
#endif
    zend_string_release(code);
    return op_array;
}

static PHP_MINIT_FUNCTION(financeos_loader)
{
    ZEND_INIT_MODULE_GLOBALS(financeos_loader, php_financeos_loader_init_globals, NULL);
    REGISTER_INI_ENTRIES();
    fos_original_compile_file = zend_compile_file;
    zend_compile_file = fos_compile_file;
    return SUCCESS;
}

static PHP_MSHUTDOWN_FUNCTION(financeos_loader)
{
    zend_compile_file = fos_original_compile_file;
    UNREGISTER_INI_ENTRIES();
    return SUCCESS;
}

static PHP_MINFO_FUNCTION(financeos_loader)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "financeos_loader support", "enabled");
    php_info_print_table_row(2, "Version", PHP_FINANCEOS_LOADER_VERSION);
    php_info_print_table_end();
}

zend_module_entry financeos_loader_module_entry = {
    STANDARD_MODULE_HEADER,
    "financeos_loader",
    NULL,
    PHP_MINIT(financeos_loader),
    PHP_MSHUTDOWN(financeos_loader),
    NULL,
    NULL,
    PHP_MINFO(financeos_loader),
    PHP_FINANCEOS_LOADER_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_FINANCEOS_LOADER
#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#endif
ZEND_GET_MODULE(financeos_loader)
#endif
