<?php
namespace HC\Shop\Services;

use HC\Shop\Notifications\Mailer as TemplatedMailer;

final class Mailer
{
    private function mailer(): TemplatedMailer { return new TemplatedMailer(); }

    public function sendOrderCreatedEmails(array $order): void
    {
        $m = $this->mailer();
        $m->sendEvent('order_created_customer', $order);
        $m->sendEvent('order_created_admin', $order);
    }

    public function sendOrderPaidEmails(array $order): void
    {
        $m = $this->mailer();
        $m->sendEvent('payment_paid_customer', $order);
        $m->sendEvent('payment_paid_admin', $order);
    }

    public function sendOrderShippedEmails(array $order): void
    {
        $m = $this->mailer();
        $m->sendEvent('shipping_started_customer', $order);
        $m->sendEvent('shipping_started_admin', $order);
    }

    public function sendOrderCancelledEmails(array $order): void
    {
        $m = $this->mailer();
        $m->sendEvent('order_cancelled_customer', $order);
        $m->sendEvent('order_cancelled_admin', $order);
    }

    public function sendOrderRefundedEmails(array $order): void
    {
        $m = $this->mailer();
        $m->sendEvent('order_refunded_customer', $order);
        $m->sendEvent('order_refunded_admin', $order);
    }
}
