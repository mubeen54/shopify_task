<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <base target="_top">
        <meta name="shopify-api-key" content="{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key', $shopDomain ?? Auth::user()->name ) }}"/>

        <title>Redirecting...</title>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                let redirectUrl = "{!! $url !!}";

                if (window.top === window.self) {
                    window.top.location.href = redirectUrl;
                } else {
                    open(redirectUrl, '_top');
                }
            });
        </script>
    </head>
    <body>
    </body>
</html>
