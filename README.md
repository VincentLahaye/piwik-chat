# Chat Plugin for Piwik
Engage people you don't know at all, with this targeted and efficient chat system, directly integrated into Piwik.

The client has these three states, plus a minimized state :

![Chat Client](/screenshots/ClientState2.png?raw=true "Chat Client State 2")
![Chat Client](/screenshots/ClientState3.png?raw=true "Chat Client State 3")
![Chat Client](/screenshots/ClientState4.png?raw=true "Chat Client State 4")

And here is the updated Visitor Profile :
![Backend](/screenshots/BackendVisitorProfile.png?raw=true "Visitor Profile in Backend")

## Installation
Add this code, after Piwik's tracking code :

    <!-- Piwik Chat -->
    <script type="text/javascript">
        (function() {
            var u=(("https:" == document.location.protocol) ? "https://{$PIWIK_URL}/" : "http://{$PIWIK_URL}/");
            var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0]; g.type='text/javascript';
            g.defer=true; g.async=true; g.src=u+'plugins/Chat/javascripts/client/client.js'; s.parentNode.insertBefore(g,s);
        })();
    </script>
    <!-- End Piwik Chat Code -->

## How it works
On the backend, this plugin modifies the visitorProfile popup, by adding to it a "Chat" tab next to "Visited pages". A new menu "Chat" is available, which allows to browse all conversations.

Soon, a reporting system will be integrated, which will show figures like conversion rate or engagement, in function of the Chat's use.

After installing the plugin, you have to modify your tracking code. It includes the file "javascripts/client/client.js", which waits for the Tracker() to initialize, then appends an iframe to the page. This iframe requests the "popout" action of the controller, where the plugin tries to identify the visitor based on his visitorID or configID.

## Changelog
v0.1 :

+   Basic chat functions with client/server communication and Piwik integration
+   Add additional inputs in the visitor profile in order to save personal informations about a visitor (name, email, phone and comments)
+   The client shows if someone of the staff is online or not
+   Sound notifications on both sides
+   French translation

## Roadmap
v0.2 :

*   Add a reporting system that shows figures about the module impact
*   Add external cache support for polling (APC, Memcache, opCache...)

v0.3 :

*   Add support for automatic messages by segment recognition

## Academical study
As an MSc student in eBusiness, this project is part of my final dissertation : "How to engage people you don't know at all ?".

The first part of this project is to develop the module and its reporting system. Then, the second part will be about analyse the impact of this Chat system (conversion rate, engagement, etc..) in function of its use, and eventually try to determine which way of use is the most efficient. From this research it may be possible to provide advices and answer questions like : What is the best behavior to adopt as an operator? What kinds of automatic messages are the more efficient in order to increase conversion?

To lead this study, I will need data! And if you support my work, you'll be able to send me your reports via the plugin, in an anonymized way.