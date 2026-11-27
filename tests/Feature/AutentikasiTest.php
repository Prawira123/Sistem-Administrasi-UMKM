<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    // 1. Admin login dengan username benar → redirect dashboard
    public function test_admin_login_dengan_username_benar_diarahkan_ke_dashboard(): void
    {
        $admin = User::factory()->administrator()->create(['username' => 'admin']);

        $response = $this->post(route('login.attempt'), [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->terakhir_login);

        $this->get(route('dashboard'))->assertOk()->assertSee($admin->nama);
    }

    // 2. Password salah → kembali ke login dengan error
    public function test_password_salah_kembali_ke_login_dengan_error(): void
    {
        User::factory()->administrator()->create(['username' => 'admin']);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => 'admin',
            'password' => 'salah',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->assertGuest();
    }

    // 3. Akun nonaktif tidak bisa login
    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        User::factory()->kasir()->nonaktif()->create(['username' => 'kasir']);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => 'kasir',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Akun Anda tidak aktif. Hubungi Administrator.']);
        $this->assertGuest();
    }

    // Akun yang dinonaktifkan saat masih login dikeluarkan pada request berikutnya
    public function test_akun_dinonaktifkan_saat_login_langsung_dikeluarkan(): void
    {
        $kasir = User::factory()->kasir()->create(['username' => 'kasir']);
        $this->actingAs($kasir)->get(route('dashboard'))->assertOk();
        $this->session(['penanda' => 'sesi-lama']);

        // Administrator menonaktifkan akun ini dari perangkat lain.
        $kasir->forceFill(['status' => User::STATUS_NONAKTIF])->save();

        $this->get(route('penjualan.create'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Pesan __('auth.inactive') tampil di halaman login. Assertion sesi (assertSessionHasErrors/Missing) sengaja
        // dipanggil setelah request ini: memanggilnya pada respons redirect membuat flash error hilang di request berikutnya.
        $this->get(route('login'))->assertOk()
            ->assertSee(__('auth.inactive'))
            ->assertSee('Akun Anda tidak aktif. Hubungi Administrator.')
            ->assertSessionMissing('penanda'); // sesi lama sudah di-invalidate
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // 4. Tamu membuka /dashboard → redirect login
    public function test_tamu_membuka_dashboard_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    // 5. Kasir membuka route khusus admin → 403
    public function test_kasir_membuka_route_khusus_admin_mendapat_403(): void
    {
        $kasir = User::factory()->kasir()->create();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($kasir)
            ->get('/supplier')
            ->assertForbidden()
            ->assertSee('Anda tidak memiliki akses ke halaman ini.');

        $this->actingAs($admin)->get('/supplier')->assertOk();
    }

    // 6. Logout menghapus sesi
    public function test_logout_menghapus_sesi(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_dibatasi_lima_kali_per_menit(): void
    {
        User::factory()->create(['username' => 'kasir']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['username' => 'kasir', 'password' => 'salah']);
        }

        $response = $this->post(route('login.attempt'), ['username' => 'kasir', 'password' => 'password']);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak percobaan login', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_gate_khusus_administrator(): void
    {
        $admin = User::factory()->administrator()->create();
        $kasir = User::factory()->kasir()->create();

        foreach (['kelola-master', 'ubah-harga-jual', 'hapus-barang', 'setujui-opname',
            'batalkan-penjualan', 'akses-keuangan', 'akses-laporan', 'akses-ai'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), $ability);
            $this->assertFalse(Gate::forUser($kasir)->allows($ability), $ability);
        }
    }

    public function test_pengguna_login_membuka_halaman_login_diarahkan_ke_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }
}
