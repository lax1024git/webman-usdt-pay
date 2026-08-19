PHP_ARG_ENABLE([financeos_loader],
  [whether to enable financeos_loader support],
  [AS_HELP_STRING([--enable-financeos-loader],
    [Enable financeos_loader: decrypt FOSENC01 PHP sources at compile time])],
  [no])

if test "$PHP_FINANCEOS_LOADER" != "no"; then
  AC_CHECK_HEADERS([openssl/evp.h openssl/sha.h], [], [
    AC_MSG_ERROR([OpenSSL headers not found (install libssl-dev / openssl-devel)])
  ])
  PHP_ADD_LIBRARY(crypto, 1, FINANCEOS_LOADER_SHARED_LIBADD)
  PHP_SUBST(FINANCEOS_LOADER_SHARED_LIBADD)
  PHP_NEW_EXTENSION(financeos_loader, financeos_loader.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
fi
