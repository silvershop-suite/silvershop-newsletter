<%-- Double-opt-in confirm email. Self-contained but styled to match the shop's shared flat transactional-email
     look (white card on a light canvas, #222 accents, shop-name/logo brand, flat black CTA — no coloured chrome).
     Kept self-contained (rather than including core's OrderEmailHeader/Footer) because this module only *suggests*
     silvershop/core and must render on any SilverStripe site; the optional logo brand still lights up via
     silvershop/store-profile's $SiteConfig.ShopEmailBrand when that module is installed. --%>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><%t SilverShop\Newsletter.ConfirmTitle "Confirm your subscription" %></title>
    <style type="text/css">
        body { margin:0; padding:0; width:100% !important; background:#f2f2f2; color:#222222; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased; }
        a { color:#222222; }
        table { border-collapse:collapse; }
        img { border:0; }
        .silvershop-email { width:600px; max-width:100%; margin:0 auto; background:#ffffff; border:1px solid #e5e5e5; }
        .silvershop-email__cell { padding:24px 28px; text-align:left; }
        .silvershop-email__logo { display:block; margin:0 0 14px; max-width:220px; height:auto; border:0; }
        .silvershop-email__brand { display:block; margin:0 0 14px; padding:0 0 14px; font-size:18px; font-weight:700; line-height:1.25; color:#222222; border-bottom:2px solid #222222; }
        .silvershop-email__title { margin:0 0 14px; font-size:22px; font-weight:700; line-height:1.25; color:#222222; }
        .silvershop-email__body { color:#444444; font-size:14px; line-height:1.6; }
        .silvershop-email__btn { display:inline-block; background:#222222; color:#ffffff; text-decoration:none; font-weight:700; padding:12px 22px; font-size:14px; }
        .silvershop-email__muted { font-size:12px; color:#888888; }
        .silvershop-email__muted a { color:#888888; }
        .silvershop-email__footer { padding:16px 28px 22px; color:#888888; font-size:12px; line-height:1.5; border-top:1px solid #ededed; }
        @media only screen and (max-width:620px){ .silvershop-email{width:100% !important;} .silvershop-email__cell,.silvershop-email__footer{padding-left:18px !important;padding-right:18px !important;} }
    </style>
</head>
<body>
    <table class="silvershop-email" cellpadding="0" cellspacing="0" border="0" role="presentation" align="center">
        <tr>
            <td class="silvershop-email__cell">
                <% if $SiteConfig.ShopEmailBrand %>$SiteConfig.ShopEmailBrand<% else %><span class="silvershop-email__brand">$SiteConfig.Title</span><% end_if %>
                <h1 class="silvershop-email__title"><%t SilverShop\Newsletter.ConfirmTitle "Confirm your subscription" %></h1>
                <div class="silvershop-email__body">
                    <p><%t SilverShop\Newsletter.ConfirmGreeting "Thanks for signing up for our newsletter." %></p>
                    <p><%t SilverShop\Newsletter.ConfirmInstruction "Please confirm your subscription by clicking the button below." %></p>
                    <p style="margin:22px 0;"><a class="silvershop-email__btn" href="$ConfirmLink"><%t SilverShop\Newsletter.ConfirmButton "Confirm my subscription" %></a></p>
                    <p class="silvershop-email__muted">
                        <%t SilverShop\Newsletter.ConfirmFallback "If the button doesn't work, copy this link into your browser:" %><br>
                        <a href="$ConfirmLink">$ConfirmLink</a>
                    </p>
                    <p class="silvershop-email__muted">
                        <%t SilverShop\Newsletter.ConfirmIgnore "If you didn't request this, you can safely ignore this email, or" %>
                        <a href="$UnsubscribeLink"><%t SilverShop\Newsletter.Unsubscribe "unsubscribe" %></a>.
                    </p>
                </div>
            </td>
        </tr>
        <tr>
            <td class="silvershop-email__footer"><% if $SiteConfig.StoreProfileName %>$SiteConfig.StoreProfileName<% else %>$SiteConfig.Title<% end_if %></td>
        </tr>
    </table>
</body>
</html>
