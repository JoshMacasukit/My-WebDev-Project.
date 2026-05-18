<?php

namespace App\Controller;

use App\Service\DataStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CustomerController extends AbstractController
{
    private function guard(SessionInterface $session)
    {
        $auth = $session->get('auth');

        if (!$auth || $auth['role'] !== 'CUSTOMER') {
            return $this->redirectToRoute('app_login');
        }

        return null;
    }

    public function home(SessionInterface $session): Response
    {
        if ($r = $this->guard($session)) return $r;
        return $this->render('customer/home.html.twig');
    }

    public function about(SessionInterface $session): Response
    {
        if ($r = $this->guard($session)) return $r;
        return $this->render('customer/about.html.twig');
    }

    public function services(SessionInterface $session): Response
    {
        if ($r = $this->guard($session)) return $r;
        return $this->render('customer/services.html.twig');
    }

    public function contact(SessionInterface $session): Response
    {
        if ($r = $this->guard($session)) return $r;
        return $this->render('customer/contact.html.twig');
    }

    public function shop(SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $data = $store->read();

        return $this->render('customer/shop.html.twig', [
            'products' => $data['products'] ?? []
        ]);
    }

    public function cart(Request $request, SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $cart = $session->get('cart', []);

        if ($request->isMethod('POST')) {

            $productId = $request->request->get('id');

            $data = $store->read();

            foreach ($data['products'] as $product) {

                if ($product['id'] === $productId && $product['stock'] > 0) {

                    $cart[] = [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'price' => $product['price']
                    ];
                }
            }

            $session->set('cart', $cart);
            return $this->redirectToRoute('app_customer_cart');
        }

        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'];
        }

        return $this->render('customer/cart.html.twig', [
            'cart' => $cart,
            'total' => $total
        ]);
    }

    public function checkout(SessionInterface $session, DataStore $store): Response
    {
        if ($r = $this->guard($session)) return $r;

        $cart = $session->get('cart', []);
        if (!$cart) {
            return $this->redirectToRoute('app_customer_shop');
        }

        $data = $store->read();
        $total = 0;

        foreach ($cart as $item) {
            $total += $item['price'];
        }

        foreach ($cart as $item) {
            foreach ($data['products'] as &$p) {
                if ($p['id'] === $item['id'] && $p['stock'] > 0) {
                    $p['stock']--;
                }
            }
        }

        $data['orders'][] = [
            'id' => $store->id('ORD_'),
            'user' => $session->get('auth')['email'],
            'items' => $cart,
            'total' => $total,
            'status' => 'Pending Delivery',
            'date' => $store->now()
        ];

        $store->log("Order placed by " . $session->get('auth')['email']);

        $store->write($data);

        $session->remove('cart');

        return $this->redirectToRoute('app_customer_home');
    }
}
