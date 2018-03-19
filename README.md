# Swift AWS SES Transport for Yii2 Swiftmailer 2.1+

 Swift AWS SES Transport with support Yii2 Swiftmailer 2.1+

# How to install

`composer require "UltimateGuitar/SwiftSesTransport"`

# Hot to use

Add to config file in your Yii2 app:


            'mailer' => [
                'class'    => \yii\swiftmailer\Mailer::class,
                'viewPath' => '@your/view/path',
                'transport' => [
                    'class' => \UltimateGuitar\SwiftSesTransport\SesTransport::class,
                    'key_id' => 'your_aws_key_id',
                    'secret_key' => 'your_aws_secret_key',
                    'endpoint' => 'your_aws_endpoint',
                ],
            ],
