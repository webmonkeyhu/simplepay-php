<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay\Traits;

use function addslashes;

trait ViewsTrait
{
    public $formDetails = [
        'id'          => 'SimplePayForm',
        'name'        => 'SimplePayForm',
        'element'     => 'button',
        'elementText' => 'Start SimplePay Payment',
    ];

    /**
     * Generates HTML submit element
     *
     * @param string $formName          The ID parameter of the form
     * @param string $submitElement     The type of the submit element ('button' or 'link' or 'auto')
     * @param string $submitElementText The label for the submit element
     */
    protected function formSubmitElement(
        string $formName = '',
        string $submitElement = 'button',
        string $submitElementText = ''
    ): string {
        switch ($submitElement) {
            case 'link':
                $element  = "\n";
                $element .= '<a href="javascript:document.getElementById(' . $formName . ').submit()">';
                $element .= addslashes($submitElementText);
                $element .= '</a>';
                break;

            case 'button':
                $element  = "\n";
                $element .= '<button type="submit">' . addslashes($submitElementText) . '</button>';
                break;

            case 'auto':
                $element  = "\n";
                $element .= '<button type="submit">' . addslashes($submitElementText) . '</button>';
                $element .= '<script language="javascript" type="text/javascript">document.getElementById(';
                $element .= $formName;
                $element .= ').submit();</script>';
                break;

            default:
                $element = "\n" . '<button type="submit">' . addslashes($submitElementText) . '</button>';
                break;
        }

        return $element;
    }

    /**
     * HTML form creation for redirect to payment page
     */
    public function getHtmlForm(): void
    {
        $this->returnData['form'] = 'Transaction start was failed!';

        if (isset($this->returnData['paymentUrl']) && $this->returnData['paymentUrl'] !== '') {
            $this->returnData['form']  = '<form action="';
            $this->returnData['form'] .= $this->returnData['paymentUrl'];
            $this->returnData['form'] .= '" method="GET" id="' . $this->formDetails['id'];
            $this->returnData['form'] .= '" accept-charset="UTF-8">';
            $this->returnData['form'] .= $this->formSubmitElement(
                $this->formDetails['name'],
                $this->formDetails['element'],
                $this->formDetails['elementText']
            );
            $this->returnData['form'] .= '</form>';
        }
    }

    /**
     * Notification based on back data
     */
    protected function backNotification(): void
    {
        $this->notificationFormated  = '<div>';
        $this->notificationFormated .= '<b>Sikertelen fizetés!</b>';

        if ($this->request['rContent']['e'] === 'SUCCESS') {
            $this->notificationFormated  = '<div>';
            $this->notificationFormated .= '<b>Sikeres fizetés</b>';
        }

        $this->notificationFormated .= '<b>SimplePay tranzakció azonosító:</b> ';
        $this->notificationFormated .= $this->request['rContent']['t'] . '</br>';
        $this->notificationFormated .= '<b>Kereskedői referencia szám:</b> ';
        $this->notificationFormated .= $this->request['rContent']['o'] . '</br>';
        $this->notificationFormated .= '</div>';
    }
}
