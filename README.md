# Chat Plugin for Piwik

## Description
Engage people you don't know at all, with this targeted and efficient chat system, directly integrated into Piwik.
This plugin modify the visitorProfile popup in the backend, by adding to it a tab "Chat" next to "Visited pages".

## Changelog
* v0.1 :
    * Basic chat functions with client/server communication and Piwik integration
    * Add additional inputs in the visitor profile in order to save personal informations about a visitor (name, email, phone and comments)
    * The client shows if someone of the staff is online or not

## Roadmap
* v0.2 : Add a reporting system that shows figures about the module impact
* v0.3 : Add support for automatic messages by segment recognition

## To DO
* Add getRequest() public access (piwik.js)
* Simplify the updated tracking code

## Installation
Update your piwik tracking code :

    <!-- Piwik -->
    <script type="text/javascript">
      var _paq = _paq || [];
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      var siteId = 2, piwikDName = "YOUR PIWIK DOMAIN";
      (function() {
        var u=(("https:" == document.location.protocol) ? "https" : "http") + "://"+ piwikDName  +"/";
        _paq.push(['setTrackerUrl', u+'piwik.php']);
        _paq.push(['setSiteId', siteId]);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0]; g.type='text/javascript';
        g.defer=false; g.async=true; g.src=u+'js/piwik.js'; s.parentNode.insertBefore(g,s);

        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0]; g.type='text/javascript';
        g.defer=false; g.async=true; g.src=u+'plugins/Chat/javascripts/client/client.js'; s.parentNode.insertBefore(g,s);
      })();

    </script>
    <noscript><p><img src="http://analytics.puissance-moteur.fr/piwik.php?idsite=1" style="border:0;" alt="" /></p></noscript>
    <!-- End Piwik Code -->
