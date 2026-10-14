<% if $SiteConfig.StoreProfileName %>$SiteConfig.StoreProfileName<% else %>$SiteConfig.Title<% end_if %>

<%t SilverShop\Newsletter.ConfirmTitle "Confirm your subscription" %>

<%t SilverShop\Newsletter.ConfirmGreeting "Thanks for signing up for our newsletter." %>
<%t SilverShop\Newsletter.ConfirmInstruction "Please confirm your subscription by clicking the button below." %>

$ConfirmLink

<%t SilverShop\Newsletter.ConfirmIgnore "If you didn't request this, you can safely ignore this email, or" %> <%t SilverShop\Newsletter.Unsubscribe "unsubscribe" %>:
$UnsubscribeLink
