<?php
namespace App\Libraries\PaymentGateway\Worldpay;

abstract class AbstractOrder {

    protected $deliveryAddress;
    protected $billingAddress;
    protected $shopperEmailAddress;
    protected $orderCodePrefix;
    protected $orderCodeSuffix;
    protected $paymentMethod;
    protected $orderDescription;
    protected $amount;
    protected $currencyCode;
    protected $name;
    protected $customerOrderCode;
    protected $token;

    public function toArray()
    {
        $this->billingAddress = $this->billingAddress->toArray();
        $this->deliveryAddress = $this->deliveryAddress->toArray();
        return get_object_vars($this);
    }

    protected function isDirectOrder()
    {
        return ((isset($this->isDirectOrder) && $this->isDirectOrder === true) ||
                (isset($this->paymentMethod) && !empty($this->paymentMethod)));
    }

    protected static function validateInputData($data)
    {
        $errors = array();

        if (empty($data)) {
            ErrorWp::throwError('ip');
        }

        if (!isset($data['token']) && !isset($data['paymentMethod'])) {
            $errors[] = ErrorWp::$errors['orderInput']['token'];
        }
        if (!isset($data['orderDescription'])) {
            $errors[] = ErrorWp::$errors['orderInput']['orderDescription'];
        }
        if (!isset($data['amount']) || ($data['amount'] > 0 && Utils::isFloat($data['amount']))) {
            $errors[] = ErrorWp::$errors['orderInput']['amount'];
        }
        if (!isset($data['currencyCode'])) {
            $errors[] = ErrorWp::$errors['orderInput']['currencyCode'];
        }
        if (!isset($data['name'])) {
            $errors[] = ErrorWp::$errors['orderInput']['name'];
        }
        if (isset($data['billingAddress']) && !is_array($data['billingAddress'])) {
            $errors[] = ErrorWp::$errors['orderInput']['billingAddress'];
        }
        if (isset($data['deliveryAddress']) && !is_array($data['deliveryAddress'])) {
            $errors[] = ErrorWp::$errors['orderInput']['deliveryAddress'];
        }

        if (count($errors) > 0) {
            ErrorWp::throwError('ip', implode(', ', $errors));
        }
    }

}
