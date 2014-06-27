/*!
 * Piwik - Web Analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
$(document).ready(function () {
    Piwik_Chat_Admin.checkNewMessage(true);
});

Piwik_Chat_Admin = (function ($, require) {
    var piwik = require('piwik'),
        xhrRequests = [],
        newMessageCount = 0;

    function scrollDown() {
        var objDiv = document.getElementById("chat-conversation");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    function initialize() {
        scrollDown();
        poll();
    }

    function displayHelp(){
        console.log('Display help');
        broadcast.propagateNewPopoverParameter('chatHelp', 1);
    }

    function getQueryParams(qs) {
        qs = qs.split("+").join(" ");

        var params = {}, tokens,
            re = /[?&]?([^=]+)=([^&]*)/g;

        while (tokens = re.exec(qs)) {
            params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
        }

        return params;
    }

    function abortRequest(key) {
        xhrRequests[key].abort();
    }

    function appendMessage(user, message) {
        var lastAuthor = $('#chat-conversation p.author').last().html();
        var html = "";

        if (user != lastAuthor) {
            html += '<p class="author">' + user + '</p>';
        }

        html += '<p>' + message + '</p>';

        $('#chat-conversation').append(html);

        scrollDown();
    }

    function sendMessage(textareaDomElement) {
        var message = $(textareaDomElement).val(),
            idVisitor = $('.visitor-profile').attr('data-visitor-id');

        if (message && idVisitor) {

            appendMessage(piwik.userLogin, message);

            $(textareaDomElement).val("");

            $.ajax({
                type: "POST",
                url: "/?module=API&method=Chat.sendMessage",
                dataType: "xml",
                cache: false,
                data: {visitorId: idVisitor, idSite: piwik.idSite, message: message, fromAdmin: true},
                success: function (data) {
                    console.log(data);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log("error: " + textStatus + " " + errorThrown);
                }
            });
        }
    }

    function setPendingMessages(data) {
        localStorage.setItem('pendingMessages', JSON.stringify(data));
    }

    function getPendingMessages() {
        return (localStorage.getItem('pendingMessages')) ? JSON.parse(localStorage.getItem('pendingMessages')) : {};
    }

    function pollChat(visitorId, microtime) {
        if(getQueryParams("module") == "coreHome"){

            xhrRequests['pollChat'] = $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.poll', visitorId: visitorId, idSite: piwik.idSite, format: 'json', microtime: microtime, fromAdmin: true},
                success: function (data) {
                    console.log(data);

                    for (var i = 0, len = data.length; i < len; i++) {
                        appendMessage("Visiteur", data[i].content, data[i].date, data[i].time);

                        if (i == (len - 1))
                            var lastMicrotime = data[i].microtime;
                    }

                    pollChat(visitorId, lastMicrotime);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    if (textStatus != "abort") {
                        setTimeout("Piwik_Chat_Popout.pollChat('" + visitorId + "', '" + microtime + "')", 15000);
                    }
                }
            });
        }
    }

    function checkNewMessage(firstLaunch, microtime) {
        if(getQueryParams("module") == "coreHome"){

            xhrRequests['pollUnread'] = $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.poll', idSite: piwik.idSite, format: 'json', microtime: microtime, fromAdmin: true},
                success: function (data) {

                    var pendingMessages = getPendingMessages(),
                        shouldWePlaySound = shouldWeDisplayNotif = false;

                    for (var i = 0, len = data.length; i < len; i++) {

                        var currentRecord = data[i]
                        visitorId = currentRecord.idvisitor;

                        if (!pendingMessages[visitorId] || currentRecord.lastsent > pendingMessages[visitorId].lastsent) {
                            shouldWePlaySound = true;

                            if (broadcast.getHash().match(/^module=Chat&action=index/g)) {
                                var getOldRow = $('.list-conversations').find("[data-visitor-id='" + visitorId + "']");

                                if (getOldRow.length > 0) {
                                    var clone = getOldRow.clone();
                                    getOldRow.remove();

                                    $('.list-conversations > tbody').prepend('<tr class="unread" data-visitor-id="' + visitorId + '">' + $(clone).html() + '</tr>');
                                } else {
                                    var clone = $('.list-conversations').find("tr").last().clone();
                                    clone.children('.idvisitor').html(visitorId);
                                    clone.children('.content').children('a').attr('data-visitor-id', visitorId);

                                    $('.list-conversations > tbody').prepend('<tr class="unread" data-visitor-id="' + visitorId + '">' + $(clone).html() + '</tr>');
                                }


                                $.get('/index.php', {module: 'API', method: 'Chat.getVisitorLastMessage', idSite: piwik.idSite, visitorId: visitorId, format: 'json'}, function (msg) {
                                    $('.list-conversations').find("[data-visitor-id='" + visitorId + "']").children('.content').children('a').html(msg[0].content);
                                    $('.list-conversations').find("[data-visitor-id='" + visitorId + "']").children('.name').html(msg[0].name);
                                    $('.list-conversations').find("[data-visitor-id='" + visitorId + "']").children('.date').html(msg[0].date + " " + msg[0].time);
                                }, 'json');
                            }

                            if (!broadcast.getHashFromUrl().match(new RegExp(visitorId))) {
                                shouldWeDisplayNotif = true;
                            }
                        }
                    }

                    if (shouldWePlaySound) {
                        playSound('notification');
                    }

                    if (!$('#Chat > a').hasClass('new-messages') && shouldWeDisplayNotif)
                        $('#Chat > a, #Chat_index > a').addClass('new-messages');


                    setPendingMessages(data);
                    checkNewMessage(false, microtime);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    if (textStatus != "abort") {
                        setTimeout("Piwik_Chat_Popout.checkNewMessage(false, '" + microtime + "')", 15000);
                    }
                }
            });
        }
    }

    function playSound(type) {

        var soundFolder = "/plugins/Chat/sounds/";
        var file;

        switch (type) {
            default:
            case 'notification':
                file = soundFolder + "woosh.mp3";
                break;
        }

        if ($("#sound-wrapper").length < 1) {
            $('body').append('<div id="sound-wrapper"></div>');
        }

        $("#sound-wrapper").html('<audio autoplay="autoplay"><source src="' + file + '" type="audio/mpeg" /><embed hidden="true" autostart="true" loop="false" src="' + file + '" /></audio>');
    }

    function clickOnProfileLink(domElement) {
        $(domElement).parent().parent().removeClass('unread');
        broadcast.propagateNewPopoverParameter('visitorProfile', $(domElement).attr('data-visitor-id'), $(domElement).attr('data-goto-chat'));

        if ($('.list-conversations .unread').length == 0)
            $('#Chat > a, #Chat_index > a').removeClass('new-messages');

        return false;
    }


    /**
     * Public
     **/
    return {
        scrollDown: function () {
            return scrollDown();
        },

        initialize: function () {
            return initialize();
        },

        checkNewMessage: function (firstLaunch, microtime) {
            return checkNewMessage(firstLaunch, microtime);
        },

        pollChat: function (visitorId, microtime) {
            return pollChat(visitorId, microtime);
        },

        abortRequest: function (key) {
            return abortRequest(key);
        },

        sendMessage: function (textareaDomElement) {
            return sendMessage(textareaDomElement);
        },

        clickOnProfileLink: function (domElement) {
            return clickOnProfileLink(domElement);
        },

        displayHelp: function () {
            return displayHelp();
        }
    }
})(jQuery, require);


/**
 * Piwik - Web Analytics
 *
 * Visitor profile popup control.
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
(function ($, require) {

    var exports = require('piwik/UI'),
        UIControl = exports.UIControl,
        VisitorProfileControl = exports.VisitorProfileControl;

    /**
     * Sets up and handles events for the visitor profile popup.
     *
     * @param {Element} element The HTML element returned by the Chat.getVisitorLog controller
     *                          action. Should have the CSS class 'visitor-profile'.
     * @constructor
     */
    function ChatVisitorProfile(element) {
        VisitorProfileControl.call(this, element);
    }

    /**
     * Initializes all elements w/ the .visitor-profile CSS class as visitor profile popups,
     * if the element has not already been initialized.
     */
    ChatVisitorProfile.initElements = function () {
        UIControl.initElements(this, '.visitor-profile');
        Piwik_Chat_Admin.pollChat($('.visitor-profile').attr('data-visitor-id'));
        Piwik_Chat_Admin.scrollDown();
    };

    $.extend(ChatVisitorProfile.prototype, VisitorProfileControl.prototype, {
        _destroy: function () {
            this.$element.removeData('uiControlObject');
            delete this.$element;

            this._baseDestroyCalled = true;
            Piwik_Chat_Admin.abortRequest('pollChat');
        },

        _bindEventCallbacks: function () {
            var self = this,
                $element = this.$element;

            $element.on('click', '.visitor-profile-close', function (e) {
                e.preventDefault();
                Piwik_Popover.close();
                return false;
            });

            $element.on('click', '.visitor-profile-more-info>a', function (e) {
                e.preventDefault();
                self._loadMoreVisits();
                return false;
            });

            $element.on('click', '.visitor-profile-see-more-cvars>a', function (e) {
                e.preventDefault();
                $('.visitor-profile-extra-cvars', $element).slideToggle();
                return false;
            });

            $element.on('click', '.visitor-profile-visit-title-row', function () {
                self._loadIndividualVisitDetails($('h2', this));
            });

            $element.on('click', '.visitor-profile-prev-visitor', function (e) {
                e.preventDefault();
                self._loadPreviousVisitor();
                return false;
            });

            $element.on('click', '.visitor-profile-next-visitor', function (e) {
                e.preventDefault();
                self._loadNextVisitor();
                return false;
            });

            $element.on('keydown', function (e) {
                if (e.which == 37) { // on <- key press, load previous visitor
                    self._loadPreviousVisitor();
                } else if (e.which == 39) { // on -> key press, load next visitor
                    self._loadNextVisitor();
                }
            });

            $element.on('click', '.visitor-profile-show-map', function (e) {
                e.preventDefault();
                self.toggleMap();
                return false;
            });

            // append token_auth dynamically to export link
            $element.on('mousedown', '.visitor-profile-export', function (e) {
                var url = $(this).attr('href');
                if (url.indexOf('&token_auth=') == -1) {
                    $(this).attr('href', url + '&token_auth=' + piwik.token_auth);
                }
            });

            // on hover, show export link (chrome won't let me do this via css :( )
            $element.on('mouseenter mouseleave', '.visitor-profile-id', function (e) {
                var $exportLink = $(this).find('.visitor-profile-export');
                if ($exportLink.css('visibility') == 'hidden') {
                    $exportLink.css('visibility', 'visible');
                } else {
                    $exportLink.css('visibility', 'hidden');
                }
            });

            /**
             * Added for chat
             **/
            $element.on('click', '.view-visitor-profile-pages-visited', function (e) {
                $(this).parent().children('.active').removeClass('active');
                $(this).parent().children('.view-visitor-profile-pages-visited').addClass('active');

                var $parent = $(this).parent().parent().parent();

                $parent.children('.visitor-profile-visits-global-container').removeClass('hide').addClass('show');
                $parent.children('.visitor-profile-chat-global-container').removeClass('show').addClass('hide');

                $.get('/index.php?module=Chat&action=setConversationViewByDefault&chat=0');
            });

            $element.on('click', '.view-visitor-profile-chat', function (e) {
                $(this).parent().children('.active').removeClass('active');
                $(this).parent().children('.view-visitor-profile-chat').addClass('active');

                var $parent = $(this).parent().parent().parent();

                $parent.children('.visitor-profile-visits-global-container').removeClass('show').addClass('hide');
                $parent.children('.visitor-profile-chat-global-container').removeClass('hide').addClass('show');

                $.get('/index.php?module=Chat&action=setConversationViewByDefault&chat=1');

                Piwik_Chat_Admin.scrollDown();
            });

            $element.on('keydown', '.visitor-profile-chat-conversation-textarea', function (e) {

                if (e.which == 13 && !e.shiftkey) { // on <- key press, load previous visitor
                    Piwik_Chat_Admin.sendMessage($(this));
                }
            });

            $element.on('submit', '#form-visitor-personnal-informations', function (e) {
                e.preventDefault();

                var visitorId = $('.visitor-profile').attr('data-visitor-id');
                $.ajax({
                    type: "POST",
                    url: "/index.php?module=API&method=Chat.updatePersonnalInformations&visitorId=" + visitorId + "&idSite=" + piwik.idSite,
                    dataType: "json",
                    cache: false,
                    data: $(this).serialize(),
                    success: function (data) {
                        console.log(data);
                    },
                });

                return false;
            });

            $element.on('load', 'body', function (e) {
                Piwik_Chat_Admin.displayHelp();
                return false;
            });
        }
    });

    exports.ChatVisitorProfile = ChatVisitorProfile;

    // update the popup handler that creates a visitor profile
    broadcast.addPopoverHandler('visitorProfile', function (visitorId, chat) {
        var url = 'module=Chat&action=getVisitorProfilePopup&visitorId=' + encodeURIComponent(visitorId);

        // if there is already a map shown on the screen, do not show the map in the popup. kartograph seems
        // to only support showing one map at a time.
        if ($('.RealTimeMap').length > 0) {
            url += '&showMap=0';
        }

        if (chat) {
            url += '&chat=1';
        }

        Piwik_Popover.createPopupAndLoadUrl(url, _pk_translate('Live_VisitorProfile'), 'visitor-profile-popup');
    });

    broadcast.addPopoverHandler('chatHelp', function (test) {
        Piwik_Popover.createPopupAndLoadUrl('module=Chat&action=help', _pk_translate('Live_VisitorProfile'), 'visitor-profile-popup');
    });

})(jQuery, require);


