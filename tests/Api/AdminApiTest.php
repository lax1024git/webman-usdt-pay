<?php

declare(strict_types=1);

namespace tests\Api;

use tests\ApiTestCase;

class AdminApiTest extends ApiTestCase
{
    public function testListAdmins(): void
    {
        $response = $this->get('/admin/admins');
        $this->assertEquals(200, $response['code']);
        $this->assertGreaterThanOrEqual(1, $response['data']['total']);
    }

    public function testCreateUpdateDeleteAdmin(): void
    {
        $create = $this->post('/admin/admins', [
            'username' => 'test_user_' . time(),
            'password' => '123456',
            'nickname' => '测试用户',
            'status' => 1,
            'role_ids' => [2],
        ], true);
        $this->assertEquals(200, $create['code']);
        $id = $create['data']['id'];

        $update = $this->put('/admin/admins/' . $id, ['nickname' => '更新昵称']);
        $this->assertEquals(200, $update['code']);

        $delete = $this->delete('/admin/admins/' . $id);
        $this->assertEquals(200, $delete['code']);
    }
}
