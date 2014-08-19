/*!
 * Piwik - Web Analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$.fn.serializeObject = function(){
    var obj = {},
        arr = this.serializeArray();

    $.each(arr, function() {
        if (obj[this.name] !== undefined) {
            if (!obj[this.name].push) {
                obj[this.name] = [obj[this.name]];
            }
            obj[this.name].push(this.value || '');
        } else {
            obj[this.name] = this.value || '';
        }
    });

    return obj;
};

$(document).ready(function () {
    Piwik_Chat_Admin.start();
});

Piwik_Chat_Admin = (function ($, require) {
    var piwik = require('piwik'),
        winTitle = window.document.title,
        shouldStopTitleNotification = false,
        titleNotificationInFunction = false;

    function start(){
        poll(true);
        bind();
        getUnreadConversations();
    }

    function bind(){
        $(window).focus(function(){
            stopTitleNotification();
        });
    }

    function scrollDown() {
        var objDiv = document.getElementById("chat-conversation");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    function displayHelp(){
        broadcast.propagateNewPopoverParameter('chatHelp', 1);
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

            textareaDomElement.val('').text('');

            var ajaxHelper = require('ajaxHelper');

            var ajax = new ajaxHelper();
            ajax.setUrl("index.php");
            ajax.addParams({module: 'API', method: 'Chat.sendMessage'}, 'GET')
            ajax.addParams({visitorId: idVisitor, idSite: piwik.idSite, message: message, fromAdmin: true}, 'POST')
            ajax.setCallback(function (data) {
                console.log(data);
            });
            ajax.setFormat('xml');
            ajax.send();
        }
    }

    function getUnreadConversations(){
        var ajaxHelper = require('ajaxHelper'),
            ajax = new ajaxHelper();

        ajax.setUrl("index.php");
        ajax.addParams({module: 'API', method: 'Chat.getUnreadConversations', idSite: piwik.idSite, format: 'json'}, 'GET');
        ajax.setCallback(function (data) {
            if(typeof data === 'object' && data.length > 0){
                displayNotificationOnTopMenu();
            }
        });
        ajax.setFormat('json'); // the expected response format
        ajax.send();
    }

    function setPendingMessages(data) {
        localStorage.setItem('pendingMessages', JSON.stringify(data));
    }

    function getPendingMessages() {
        return (localStorage.getItem('pendingMessages')) ? JSON.parse(localStorage.getItem('pendingMessages')) : {};
    }

    function poll(microtime){
        if(broadcast.getParamValue('module', window.location.href) == "CoreHome"){
            $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.poll', idSite: piwik.idSite, microtime: microtime, fromAdmin: true, format: 'json'},
                success: function (data) {
                    var pendingMessages = getPendingMessages(),
                        shouldWeDisplayNotif = false,
                        visitorIdFocused = $('.visitor-profile').attr('data-visitor-id');

                    poll(microtime);

                    if(data.value === false || data.length < 1){
                        return false;
                    }

                    playSound('notification');

                    if(titleNotificationInFunction == false){
                        shouldStopTitleNotification = false;
                        showTitleNotification();
                    }

                    if (broadcast.getHash().match(/module=Chat&action=index/g) != null) {

                        for (var i = 0, len = data.length; i < len; i++) {

                            var currentRecord = data[i],
                                visitorId = currentRecord.idvisitor;

                            if (!pendingMessages[visitorId] || currentRecord.lastsent > pendingMessages[visitorId].lastsent) {

                                /**
                                 * If the VisitorProfile popup if open
                                 */
                                if(visitorIdFocused && visitorIdFocused == visitorId){
                                    appendMessage(_pk_translate('Chat_Visitor'), data[i].content, data[i].date, data[i].time);

                                    // Remove 'unread' class
                                    $('.list-conversations').find("[data-visitor-id='" + visitorId + "']").removeClass('unread');
                                } else {
                                    /**
                                     * If we are on the Chat module index
                                     */
                                    if (broadcast.getHash().match(/module=Chat&action=index/g).length != null) {
                                        broadcast.propagateAjax(broadcast.getHash());
                                    }
                                }

                                if (!broadcast.getHashFromUrl().match(new RegExp(visitorId))) {
                                    shouldWeDisplayNotif = true;
                                }
                            }
                        }
                    } else {
                        shouldWeDisplayNotif = true;
                    }

                    if(shouldWeDisplayNotif){
                        displayNotificationOnTopMenu();
                    }

                    setPendingMessages(data);
                }
            });
        }
    }

    function displayNotificationOnTopMenu(){
        if (!$('#Chat > a').hasClass('new-messages')) {
            $('#Chat > a, #Chat_index > a').addClass('new-messages');
        }
    }

    function hideNotificationOnTopMenu(){
        $('#Chat > a, #Chat_index > a').removeClass('new-messages');
    }

    function showTitleNotification(){
        if(shouldStopTitleNotification === true){
            window.document.title = winTitle;
            titleNotificationInFunction = false;
            return false;
        }

        titleNotificationInFunction = true;

        window.document.title = (window.document.title === winTitle) ? "New chat messages! " + winTitle : winTitle;

        setTimeout(function(){ return showTitleNotification(); }, 1000);
    }

    function stopTitleNotification(){
        shouldStopTitleNotification = true;
    }

    function playSound(type) {
        var soundFolder = "/plugins/Chat/sounds/",
            file;

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
        broadcast.propagateNewPopoverParameter('visitorProfile', $(domElement).attr('data-visitor-id') + '|' + $(domElement).attr('data-goto-chat'));

        if ($('.list-conversations .unread').length == 0){
            hideNotificationOnTopMenu();
        }

        return false;
    }


    /**
     * Public
     **/
    return {
        scrollDown: function () {
            return scrollDown();
        },

        start: function () {
            return start();
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
        Piwik_Chat_Admin.scrollDown();

        var state = localStorage.getItem('PressEnterToSubmit');

        if(state == '1'){
            $('#press-enter-to-submit').prop('checked', true);
            $('#button-submit-conversation-textarea').attr('disabled', 'disabled');
        }
    };

    $.extend(ChatVisitorProfile.prototype, VisitorProfileControl.prototype, {
        _destroy: function () {
            this.$element.removeData('uiControlObject');
            delete this.$element;

            this._baseDestroyCalled = true;
            broadcast.propagateAjax(broadcast.getHash());

            if ($('.list-conversations').length > 0 && $('.list-conversations .unread').length == 0){
                hideNotificationOnTopMenu();
            }
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
                if (e.which == 37 && $('.visitor-profile-chat-conversation-textarea').is(':focus') === false) { // on <- key press, load previous visitor
                    self._loadPreviousVisitor();
                } else if (e.which == 39 && $('.visitor-profile-chat-conversation-textarea').is(':focus') === false) { // on -> key press, load next visitor
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

            var tooltipIsOpened = false;

            $('a', $element).on('focus', function () {
                // see https://github.com/piwik/piwik/issues/4099
                if (tooltipIsOpened) {
                    $element.tooltip('close');
                }
            });

            $element.tooltip({
                track: true,
                show: false,
                hide: false,
                content: function() {
                    var title = $(this).attr('title');
                    return $('<a>').text( title ).html().replace(/\n/g, '<br />');
                },
                tooltipClass: 'small',
                open: function() { tooltipIsOpened = true; },
                close: function() { tooltipIsOpened = false; }
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

                var ajaxHelper = require('ajaxHelper');

                var ajax = new ajaxHelper();
                ajax.setUrl("index.php");
                ajax.addParams({module: 'Chat', action: 'setConversationViewByDefault', chat: false}, 'GET');
                ajax.send();
            });

            $element.on('click', '.view-visitor-profile-chat', function (e) {
                $(this).parent().children('.active').removeClass('active');
                $(this).parent().children('.view-visitor-profile-chat').addClass('active');

                var $parent = $(this).parent().parent().parent();

                $parent.children('.visitor-profile-visits-global-container').removeClass('show').addClass('hide');
                $parent.children('.visitor-profile-chat-global-container').removeClass('hide').addClass('show');

                var ajaxHelper = require('ajaxHelper');

                var ajax = new ajaxHelper();
                ajax.setUrl("index.php");
                ajax.addParams({module: 'Chat', action: 'setConversationViewByDefault', chat: true}, 'GET');
                ajax.send();

                Piwik_Chat_Admin.scrollDown();
            });

            $element.on('change', '#press-enter-to-submit', function (e) {
                var state;

                if(($(this).prop('checked') == true)){
                    $('#button-submit-conversation-textarea').attr('disabled', 'disabled');
                    state = 1;
                } else {
                    $('#button-submit-conversation-textarea').prop('disabled', false);
                    state = 0;
                }

                localStorage.setItem('PressEnterToSubmit', state);
            });

            $element.on('keydown', '.visitor-profile-chat-conversation-textarea', function (e) {
                if (e.which == 13 && !e.shiftKey && $("#press-enter-to-submit").prop("checked") == true) {
                    e.preventDefault();
                    Piwik_Chat_Admin.sendMessage($(this));
                    e.stopPropagation();
                }
            });

            $element.on('click', '#button-submit-conversation-textarea', function (e) {
                if ($("#press-enter-to-submit").prop("checked") == false) {
                    Piwik_Chat_Admin.sendMessage($('.visitor-profile-chat-conversation-textarea'));
                }
            });

            $element.on('submit', '#form-visitor-personnal-informations', function (e) {
                e.preventDefault();

                $('.whats-happening').html('Please wait..');

                var visitorId = $('.visitor-profile').attr('data-visitor-id');

                var ajaxHelper = require('ajaxHelper');

                var ajax = new ajaxHelper();
                ajax.setUrl("index.php");
                ajax.addParams({module: 'API', method: 'Chat.updatePersonnalInformations', visitorId: visitorId, idSite: piwik.idSite, format: 'json'}, 'GET');
                ajax.addParams($(this).serializeObject(), 'POST');
                ajax.setCallback(function (data) {
                    console.log(data);
                    $('.whats-happening').html('Success !');
                    setTimeout(function(){
                        $('.whats-happening').html('');
                    }, 4000);
                });
                ajax.setFormat('json'); // the expected response format
                ajax.send();

                return false;
            });

            $element.on('load', 'body', function (e) {
                Piwik_Chat_Admin.displayHelp();
                return false;
            });
        },
    });

    exports.ChatVisitorProfile = ChatVisitorProfile;

    // update the popup handler that creates a visitor profile
    broadcast.addPopoverHandler('visitorProfile', function (paramsString) {

        var params = paramsString.split('|'),
            visitorId = params[0],
            chat = params[1];

        var url = 'module=Chat&action=getVisitorProfilePopup&visitorId=' + encodeURIComponent(visitorId);

        // if there is already a map shown on the screen, do not show the map in the popup. kartograph seems
        // to only support showing one map at a time.
        if ($('.RealTimeMap').length > 0) {
            url += '&showMap=0';
        }

        if (chat !== 'undefined' && chat != '') {
            url += '&chat=1';
        }

        Piwik_Popover.createPopupAndLoadUrl(url, _pk_translate('Live_VisitorProfile'), 'visitor-profile-popup');
    });

    broadcast.addPopoverHandler('chatHelp', function (test) {
        Piwik_Popover.createPopupAndLoadUrl('module=Chat&action=help', 'Chat Help', 'chat-help-popup');
    });

    broadcast.addPopoverHandler('addAutomaticMessage', function (test) {
        Piwik_Popover.createPopupAndLoadUrl('module=Chat&action=addOrUpdateAutomaticMessage', 'Add automatic message', 'add-auto-message-popup');
    });

    broadcast.addPopoverHandler('updateAutomaticMessage', function (id) {
        Piwik_Popover.createPopupAndLoadUrl('module=Chat&action=addOrUpdateAutomaticMessage&idAutoMsg=' + id, 'Update automatic message', 'update-auto-message-popup');
    });

})(jQuery, require);
