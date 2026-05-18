<?php

namespace App\Controller;

use App\Service\DataStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthController extends AbstractController
{
    private int $timeout = 1800;

    public function login(Request $request, SessionInterface $session, DataStore $store): Response
    {
        $data = $store->read();

        if ($request->isMethod('POST')) {

            $email = trim($request->request->get('email', ''));
            $password = trim($request->request->get('password', ''));

            if (!$email || !$password) {
                $this->addFlash('error', 'Login Denied: Email and password are required.');
                return $this->redirectToRoute('app_login');
            }

            if ($email === ($data['admin']['email'] ?? '')) {

                if (password_verify($password, $data['admin']['password'])) {

                    $session->set('auth', [
                        'role' => 'ADMIN',
                        'email' => $email,
                        'last_activity' => time()
                    ]);

                    $store->log("Admin logged in");

                    $this->addFlash('success', 'Login Successful: Welcome Administrator.');
                    return $this->redirectToRoute('app_admin');
                }

                $this->addFlash('error', 'Login Denied: Incorrect admin password.');
                return $this->redirectToRoute('app_login');
            }

            foreach ($data['users'] as &$user) {

                if ($user['email'] === $email) {

                    if ($user['status'] !== 'Enabled') {
                        $this->addFlash('error', 'Login Denied: Your account is disabled.');
                        return $this->redirectToRoute('app_login');
                    }

                    if (!password_verify($password, $user['password'])) {
                        $this->addFlash('error', 'Login Denied: Incorrect password.');
                        return $this->redirectToRoute('app_login');
                    }

                    $user['history'][] = "Login at " . $store->now();
                    $store->write($data);

                    $session->set('auth', [
                        'role' => 'CUSTOMER',
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'name' => $user['name'],
                        'last_activity' => time()
                    ]);

                    $store->log("Customer logged in: " . $user['email']);

                    $this->addFlash('success', 'Login Successful: Welcome back, ' . $user['name'] . '!');
                    return $this->redirectToRoute('app_customer_home');
                }
            }

            $this->addFlash('error', 'Login Denied: Email not found.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/login.html.twig');
    }

    public function signup(Request $request, DataStore $store): Response
    {
        $data = $store->read();

        if ($request->isMethod('POST')) {

            $name = trim($request->request->get('name',''));
            $email = trim($request->request->get('email',''));
            $password = trim($request->request->get('password',''));

            if (!$name || !$email || !$password) {
                $this->addFlash('error', 'Sign Up Denied: All fields are required.');
                return $this->redirectToRoute('app_signup');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Sign Up Denied: Invalid email format.');
                return $this->redirectToRoute('app_signup');
            }

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Sign Up Denied: Password must be at least 6 characters long.');
                return $this->redirectToRoute('app_signup');
            }

            foreach ($data['users'] as $user) {
                if ($user['email'] === $email) {
                    $this->addFlash('error', 'Sign Up Denied: Email is already registered.');
                    return $this->redirectToRoute('app_signup');
                }
            }

            $data['users'][] = [
                'id' => $store->id('USR'),
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'Enabled',
                'history' => ["Account created at " . $store->now()]
            ];

            $store->write($data);
            $store->log("New user registered: " . $email);

            $this->addFlash('success', 'Sign Up Successful: You may now login.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/signup.html.twig');
    }

    public function logout(SessionInterface $session): Response
    {
        $session->clear();
        $this->addFlash('success', 'You have been logged out successfully.');
        return $this->redirectToRoute('app_login');
    }
}
