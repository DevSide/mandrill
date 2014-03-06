#Mandrill module for Koahana 3

This module allows you to communicate with Mandrill API 1.0

See [Mandrill API documentation](https://github.com/kohana/kohana)
---

##Requirements
* PHP Curl extension

##Example

Asynchronous send with two recipients.

```php
    // Email template
    $view = View::factory('mailing/template');

    // Email parameters
    $params = array(
        'html' => $view->render(),
        'subject' => 'Test template',
        'from_email' => 'mr_x@domain.com',
        'from_name' => 'Mr X',
        'to' => array(
            array(
                'email' => 'foo@domain.com'
            ),
            array(
                'email' => 'bar@domain.com'
            )
        ),
        'preserve_recipients' => false,
        'metadata' => array(
            'page_id' => 1,
        ),
        'recipient_metadata' => array(
            array(
                "rcpt" => "foo@domain.com",
                "values" => array("id" => 1)
            ),
            array(
                "rcpt" => "bar@domain.com",
                "values" => array("id" => 2)
            ),
        )
    );

    // Send asynchronous email and receive Mandrill response
    $resultJson = Mandrill::instance()->call(array(
        'type' => 'messages',
        'call' => 'send',
        'message' => $params,
        'async' => true
    ));
```