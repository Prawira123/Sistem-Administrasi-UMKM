<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_halaman_utama_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_login_dapat_dibuka(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Username');
    }
}
