<?php

declare(strict_types=1);

namespace Fahri\LapakId\Controllers;

use Fahri\LapakId\Core\Auth;
use Fahri\LapakId\Core\Controller;
use Fahri\LapakId\Core\Flash;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(): string
    {
        if (Auth::check()) {
            if (Auth::isAdmin()) {
                $this->redirect('/admin');
            }
            $this->redirect('/products');
        }

        return $this->render('front/auth/login.twig', [
            'title' => 'Login',
            'form' => ['email' => ''],
            'error' => null,
            'databaseError' => null,
        ]);
    }

    public function login(): string
    {
        $email = strtolower($this->input('email'));
        $password = trim((string) ($_POST['password'] ?? ''));

        if ($email === '' || $password === '') {
            return $this->render('front/auth/login.twig', [
                'title' => 'Login',
                'form' => ['email' => $email],
                'error' => 'Email and password are required.',
                'databaseError' => null,
            ]);
        }

        try {
            if (Auth::attempt($this->db(), $email, $password)) {
                Flash::set('success', 'Welcome back.');
                if (Auth::isAdmin()) {
                    $this->redirect('/admin');
                }
                $this->redirect('/products');
            }
        } catch (Throwable $throwable) {
            return $this->render('front/auth/login.twig', [
                'title' => 'Login',
                'form' => ['email' => $email],
                'error' => null,
                'databaseError' => $throwable->getMessage(),
            ]);
        }

        return $this->render('front/auth/login.twig', [
            'title' => 'Login',
            'form' => ['email' => $email],
            'error' => 'Invalid credentials.',
            'databaseError' => null,
        ]);
    }

    public function showRegister(): string
    {
        if (Auth::check()) {
            if (Auth::isAdmin()) {
                $this->redirect('/admin');
            }
            $this->redirect('/products');
        }

        return $this->render('front/auth/register.twig', [
            'title' => 'Register',
            'form' => ['fullname' => '', 'email' => ''],
            'error' => null,
            'databaseError' => null,
        ]);
    }

    public function register(): string
    {
        $fullname = trim($this->input('fullname'));
        $email = strtolower($this->input('email'));
        $password = trim((string) ($_POST['password'] ?? ''));
        $passwordConfirm = trim((string) ($_POST['password_confirm'] ?? ''));

        if ($fullname === '' || $email === '' || $password === '') {
            return $this->render('front/auth/register.twig', [
                'title' => 'Register',
                'form' => ['fullname' => $fullname, 'email' => $email],
                'error' => 'All fields are required.',
                'databaseError' => null,
            ]);
        }

        if ($password !== $passwordConfirm) {
            return $this->render('front/auth/register.twig', [
                'title' => 'Register',
                'form' => ['fullname' => $fullname, 'email' => $email],
                'error' => 'Passwords do not match.',
                'databaseError' => null,
            ]);
        }

        try {
            $existing = $this->db()->selectOne(
                'SELECT id FROM users WHERE email = :email LIMIT 1',
                ['email' => $email]
            );

            if ($existing) {
                return $this->render('front/auth/register.twig', [
                    'title' => 'Register',
                    'form' => ['fullname' => $fullname, 'email' => $email],
                    'error' => 'Email is already registered.',
                    'databaseError' => null,
                ]);
            }

            $this->db()->execute(
                'INSERT INTO users (fullname, email, password, role) VALUES (:fullname, :email, :password, :role)',
                [
                    'fullname' => $fullname,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'user',
                ]
            );

            Auth::attempt($this->db(), $email, $password);
            Flash::set('success', 'Registration successful. Welcome!');
            $this->redirect('/products');

        } catch (Throwable $throwable) {
            return $this->render('front/auth/register.twig', [
                'title' => 'Register',
                'form' => ['fullname' => $fullname, 'email' => $email],
                'error' => null,
                'databaseError' => $throwable->getMessage(),
            ]);
        }
    }

    public function logout(): void
    {
        Auth::logout();
        Flash::set('success', 'You have been logged out.');
        $this->redirect('/login');
    }
}
