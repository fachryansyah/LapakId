<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Auth;
use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Flash;
use Throwable;

class ProfileController extends Controller
{
    public function index(): string
    {
        if (!Auth::check()) {
            Flash::set('error', 'Silakan login terlebih dahulu.');
            $this->redirect('/login');
        }

        $user = Auth::user();

        return $this->render('front/profile.twig', [
            'title' => 'Profil Saya',
            'form' => [
                'fullname' => $user['fullname'] ?? '',
                'email' => $user['email'] ?? '',
            ],
            'error' => null,
        ]);
    }

    public function update(): string
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $user = Auth::user();
        $fullname = trim($this->input('fullname'));
        $email = strtolower(trim($this->input('email')));
        $password = trim((string) ($_POST['password'] ?? ''));
        $passwordConfirm = trim((string) ($_POST['password_confirm'] ?? ''));

        if ($fullname === '' || $email === '') {
            return $this->render('front/profile.twig', [
                'title' => 'Profil Saya',
                'form' => ['fullname' => $fullname, 'email' => $email],
                'error' => 'Nama lengkap dan email wajib diisi.',
            ]);
        }

        if ($password !== '' && $password !== $passwordConfirm) {
            return $this->render('front/profile.twig', [
                'title' => 'Profil Saya',
                'form' => ['fullname' => $fullname, 'email' => $email],
                'error' => 'Konfirmasi password tidak cocok.',
            ]);
        }

        try {
            // Check if email exists for other users
            $existing = $this->db()->selectOne(
                'SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1',
                ['email' => $email, 'id' => $user['id']]
            );

            if ($existing) {
                return $this->render('front/profile.twig', [
                    'title' => 'Profil Saya',
                    'form' => ['fullname' => $fullname, 'email' => $email],
                    'error' => 'Email sudah digunakan oleh akun lain.',
                ]);
            }

            if ($password !== '') {
                $this->db()->execute(
                    'UPDATE users SET fullname = :fullname, email = :email, password = :password WHERE id = :id',
                    [
                        'fullname' => $fullname,
                        'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $user['id']
                    ]
                );
            } else {
                $this->db()->execute(
                    'UPDATE users SET fullname = :fullname, email = :email WHERE id = :id',
                    [
                        'fullname' => $fullname,
                        'email' => $email,
                        'id' => $user['id']
                    ]
                );
            }

            // Update session
            $updatedUser = $this->db()->selectOne('SELECT id, fullname, email, role, created_at FROM users WHERE id = :id', ['id' => $user['id']]);
            Auth::login($updatedUser);

            Flash::set('success', 'Profil berhasil diperbarui.');
            $this->redirect('/profile');

        } catch (Throwable $throwable) {
            return $this->render('front/profile.twig', [
                'title' => 'Profil Saya',
                'form' => ['fullname' => $fullname, 'email' => $email],
                'error' => 'Terjadi kesalahan sistem: ' . $throwable->getMessage(),
            ]);
        }
    }
}