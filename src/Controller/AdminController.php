<?php

namespace App\Controller;

use App\Service\DataStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AdminController extends AbstractController
{
    private int $timeout = 1800;

    private function guard(SessionInterface $session)
    {
        $auth = $session->get('auth');

        if (!$auth || $auth['role'] !== 'ADMIN') {
            return $this->redirectToRoute('app_login');
        }

        if (time() - ($auth['last_activity'] ?? 0) > $this->timeout) {
            $session->clear();
            return $this->redirectToRoute('app_login');
        }

        $auth['last_activity'] = time();
        $session->set('auth', $auth);

        return null;
    }

    public function dashboard(SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $d = $store->read();

        $totalSales = 0;
        $delivered = 0;

        foreach ($d['orders'] as $o) {
            if (($o['status'] ?? '') === 'Delivered') {
                $delivered++;
                $totalSales += $o['total'] ?? 0;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'users' => count($d['users']),
            'products' => count($d['products']),
            'orders' => count($d['orders']),
            'delivered' => $delivered,
            'sales' => $totalSales,
            'logs' => array_reverse($d['logs'])
        ]);
    }

    public function products(Request $request, SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $d = $store->read();

        if ($request->isMethod('POST')) {

            $action = $request->request->get('action');

            if ($action === 'add') {
                $d['products'][] = [
                    'id' => $store->id('PROD_'),
                    'name' => trim($request->request->get('name')),
                    'price' => (float)$request->request->get('price'),
                    'stock' => (int)$request->request->get('stock')
                ];
                $store->log("Product added");
            }

            if ($action === 'edit') {
                foreach ($d['products'] as &$p) {
                    if ($p['id'] === $request->request->get('id')) {
                        $p['name'] = trim($request->request->get('name'));
                        $p['price'] = (float)$request->request->get('price');
                        $p['stock'] = (int)$request->request->get('stock');
                        $store->log("Product edited");
                    }
                }
            }

            if ($action === 'delete') {
                $d['products'] = array_values(array_filter(
                    $d['products'],
                    fn($p) => $p['id'] !== $request->request->get('id')
                ));
                $store->log("Product deleted");
            }

            $store->write($d);
            return $this->redirectToRoute('app_admin_products');
        }

        return $this->render('admin/products.html.twig', [
            'products' => $d['products']
        ]);
    }

    public function orders(Request $request, SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $d = $store->read();

        if ($request->isMethod('POST')) {

            foreach ($d['orders'] as &$o) {

                if ($o['id'] === $request->request->get('id')) {

                    $action = $request->request->get('action');

                    if ($action === 'status') {
                        $o['status'] = $request->request->get('status');
                        $store->log("Order status updated");
                    }

                    if ($action === 'cancel') {
                        $o['status'] = 'Cancelled';
                        $store->log("Order cancelled");
                    }
                }
            }

            $store->write($d);
            return $this->redirectToRoute('app_admin_orders');
        }

        return $this->render('admin/orders.html.twig', [
            'orders' => $d['orders']
        ]);
    }

    public function users(Request $request, SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $d = $store->read();

        if ($request->isMethod('POST')) {

            foreach ($d['users'] as &$u) {

                if ($u['id'] === $request->request->get('id')) {

                    $action = $request->request->get('action');

                    if ($action === 'edit') {
                        $u['name'] = trim($request->request->get('name'));
                        $u['email'] = trim($request->request->get('email'));
                        $u['history'][] = "Profile updated";
                        $store->log("User edited");
                    }

                    if ($action === 'toggle') {
                        $u['status'] = $u['status'] === 'Enabled'
                            ? 'Disabled'
                            : 'Enabled';

                        $u['history'][] = "Account {$u['status']}";
                        $store->log("User status changed");
                    }
                }
            }

            $store->write($d);
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users.html.twig', [
            'users' => $d['users']
        ]);
    }

    public function reports(SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $d = $store->read();

        $sales = 0;
        $delivered = 0;

        foreach ($d['orders'] as $o) {
            if (($o['status'] ?? '') === 'Delivered') {
                $delivered++;
                $sales += $o['total'] ?? 0;
            }
        }

        return $this->render('admin/reports.html.twig', [
            'users' => count($d['users']),
            'products' => count($d['products']),
            'orders' => count($d['orders']),
            'deliveries' => $delivered,
            'sales' => $sales,
            'logs' => array_reverse($d['logs'])
        ]);
    }

}
