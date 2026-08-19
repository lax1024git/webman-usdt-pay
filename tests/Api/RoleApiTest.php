<?php

declare(strict_types=1);

namespace tests\Api;

use tests\ApiTestCase;

class RoleApiTest extends ApiTestCase
{
    public function testListRoles(): void
    {
        $response = $this->get('/admin/roles');
        $this->assertEquals(200, $response['code']);
        $this->assertGreaterThanOrEqual(2, $response['data']['total']);
    }

    public function testRolePermissions(): void
    {
        $response = $this->get('/admin/roles/2/permissions');
        $this->assertEquals(200, $response['code']);
        $this->assertArrayHasKey('permission_ids', $response['data']);
    }

    public function testAssignRolePermissions(): void
    {
        $response = $this->put('/admin/roles/2/permissions', [
            'permission_ids' => [1101, 1201, 2201],
        ]);
        $this->assertEquals(200, $response['code']);

        $permissions = $this->get('/admin/roles/2/permissions');
        $this->assertEquals(200, $permissions['code']);
        $this->assertContains(1101, $permissions['data']['permission_ids']);
    }
}
