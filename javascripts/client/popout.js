function Piwik_Chat_Popout() {

    var __tr,   // Translation table
        state,  // Popout state
        socket, // easyXDM socket
        staffAlreadyAfk,
        lastNameStaff,
        lastStaffMessage,
        localLanguage,
        idvisitor = null,
        idsite = null,
        domConversation = $('#chat-conversation');

    function initialize() {

        domConversation.html('<p style="margin:20px; text-align:center;">Loading messages...</p>');

        var messages = getMessages(),
            microtimeFrom = false;

        if (messages != null) {
            microtimeFrom = getLastReceivedMessageMicrotime(); // Get last message microtime in order to get new message from this microtime
        }

        $.ajax({
            type: "GET",
            url: "/index.php",
            dataType: "json",
            cache: false,
            data: {module: 'API', method: 'Chat.getMessages', idSite: idsite, visitorId: idvisitor, format: 'json', microtimeFrom: microtimeFrom},
            success: function (newMessages) {
                if (messages != null) {
                    appendToMessages(newMessages);
                } else {
                    setMessages(newMessages);
                }

                populateConversation(getMessages(), {}, true);
            }
        });

        if ($.inArray(getState(), ['1', '2', '3', '4']) === -1) {
            if (domConversation.children().length == 0) {
                setState(2); // Need some help ?
            } else {
                setState(4); // Display conversation
            }
        }

        $('.chat-state-' + getState()).show();
        scrollDown();

        socket.postMessage(getState());

        bindEventCallbacks();
        isStaffAFK();
        poll();
    }

    function populateConversation(messages, lastMessage, reInit){
        if(reInit == true)
            domConversation.html('');

        if(typeof lastMessage === 'undefined')
            lastMessage = {};

        $.each(messages, function (key, message) {
            appendMessage(message, lastMessage);
            lastMessage = message;
        });

        scrollDown();
    }

    function isStaffAFK() {
        if (getState() == 4) {
            var lastTimeMsgStaff = parseInt($(".author:not(:contains('" + __tr['You'] + "'))").last().parent().next().attr('data-microtime'));

            if (lastTimeMsgStaff < parseInt((Date.now() / 1000) - (2 * 60 * 60)) && !$("#chat-conversation p.has-join-or-quit").last().hasClass('offline')) {
                domConversation.append('<p class="has-join-or-quit offline">' + $(".author:not(:contains('" + __tr['You'] + "'))").last().html() + ' has quit this session.</p>');
                staffAlreadyAfk = true;

                scrollDown();
            }
        }

        if (getState() != 1) {

            $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.isStaffOnline', idSite: idsite, format: 'json'},
                success: function (result) {
                    if (result.value === true) {
                        $('.is-staff-online').html('<div class="yes"><span class="circle"></span> ' + __tr['StaffOnline'] + '</div>');
                        $('.chat-header .circle').show();
                        $('.chat-state-3 .notice').hide();
                    } else {
                        $('.is-staff-online').html('');
                        $('.chat-header .circle').hide();
                        $('.chat-state-3 .notice').show();
                    }
                }
            });
        }

        setTimeout(function () {
            return isStaffAFK();
        }, 20000); // 20 seconds
    }

    function scrollDown() {
        var objDiv = document.getElementById("chat-conversation");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    function appendMessage(message, lastMessage) {

        var displayAuthor = false,
            currentAuthor = (message.answerfrom !== 'undefined' && message.answerfrom !== null) ? message.answerfrom : __tr['You'],
            lastAuthor = (lastMessage.answerfrom !== 'undefined' && lastMessage.answerfrom !== null) ? lastMessage.answerfrom : __tr['You'],
            html = "";

        if(typeof message.moment === 'undefined' && typeof message.microtime !== 'undefined'){
            var splitCurMsgMicrotime = message.microtime.split('.');
            message.moment = moment.unix(splitCurMsgMicrotime[0]);
        }

        if(typeof lastMessage.moment === 'undefined' && typeof lastMessage.microtime !== 'undefined'){
            var splitOldMsgMicrotime = lastMessage.microtime.split('.');
            lastMessage.moment = moment.unix(splitOldMsgMicrotime[0]);
        }

        if (message.idautomsg != null)
            console.log(message);

        if (staffAlreadyAfk == false && lastStaffMessage.microtime < (message.microtime - (2 * 60 * 60)) && lastMessage.idautomsg == null) {
            domConversation.append('<p class="has-join-or-quit">' + lastStaffMessage.answerfrom + ' ' + __tr['HasQuit'] + '</p>');
            staffAlreadyAfk = true;
            displayAuthor = true;
        }

        if (message.answerfrom && (staffAlreadyAfk == true || lastMessage === 'undefined' || lastMessage.answerfrom === null) && message.idautomsg == null) {
            domConversation.append('<p class="has-join-or-quit" title="' + message.moment.format('LLL') + '">' + message.answerfrom + ' ' + __tr['HasJoin'] + '</p>');
            staffAlreadyAfk = false;
            displayAuthor = true;
        }

        if (currentAuthor != lastAuthor || $("#chat-conversation p").last().hasClass('has-join-or-quit') == true) {
            console.log((currentAuthor != lastAuthor || $("#chat-conversation p").last().hasClass('has-join-or-quit') == true));
            displayAuthor = true;
        }

        if (displayAuthor == true) {
            html += '<p class="author-container"><span class="author">' + currentAuthor + '</span><span class="microtime">' + message.moment.fromNow() + '</span></p>';
        }

        html += '<p class="chat-msg" data-microtime="' + message.microtime + '" title="' + message.moment.format('LLL') + '">' + message.content + '<span class="microtime"></span></p>';

        if (message.answerfrom != null && message.idautomsg == null) {
            lastStaffMessage = message;
        }

        domConversation.append(html);
        scrollDown();
    }

    function poll(microtime) {
        if (getState() == 1 || getState() == 4) {
            $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.poll', visitorId: idvisitor, idSite: idsite, format: 'json', microtime: microtime},
                success: function (newMessages) {
                    console.log(newMessages);

                    if(newMessages.value === false){
                        var microtime = getLastReceivedMessageMicrotime();
                    } else {
                        var microtime = newMessages[newMessages.length - 1]['microtime'];

                        appendToMessages(newMessages);

                        if (getState() != 4)
                            maximizePopout();

                        populateConversation(newMessages, getLastReceivedMessage());

                        playSound('notification');
                    }

                    poll(microtime);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log("error: " + textStatus + " " + errorThrown);
                    setTimeout(function () {
                        return poll();
                    }, 15000);
                }
            });
        }
    }

    function sendMessage(textareaDomElement) {
        var textareaVal = $(textareaDomElement).val();

        if (textareaVal != "") {
            var newMessage = {moment: moment(), content: textareaVal, answerfrom: null, idmessage: null}

            appendMessage(newMessage, getLastReceivedMessage());

            $(textareaDomElement).val("");

            $.ajax({
                type: "POST",
                url: "/?module=API&method=Chat.sendMessage&format=json",
                dataType: "json",
                cache: false,
                data: {visitorId: idvisitor, idSite: idsite, message: newMessage.content},
                success: function (message) {
                    console.log(message);
                    appendToMessages(message);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log("error: " + textStatus + " " + errorThrown);
                }
            });
        }
    }

    function submitMessageFromStep2(msg) {
        $('.chat-state-3 .chat-input').html(msg);

        $('.chat-state-2').hide();
        $('.chat-state-3').show();

        $('.chat-state-3 .name').focus();

        setState(3);
    }

    function bindEventCallbacks() {
        $('.chat-input').on('keydown', function (e) {
            if (e.which == 13 && !e.shiftKey) { // on <- key press, load previous visitor
                e.preventDefault();
                sendMessage($(this));
            }
        });

        $('#form-chat-state-2').submit(function (e) {
            e.preventDefault();
            submitMessageFromStep2($(this).children('.chat-state-2-input').val());
            return false;
        });

        $('#form-chat-state-3').submit(function (e) {
            e.preventDefault();

            $('.chat-state-3').hide();
            $('.chat-state-4').show();

            updatePersonnalInformations($(this).find('.name').val(), $(this).find('.email').val());
            sendMessage($(this).find('.chat-input'));

            setState(4);
            poll();

            return false;
        });

        $('.chat-state-1 .action-logo').on('click', function (e) {
            maximizePopout();
        });

        $('.chat-state-4 .action-logo').on('click', function (e) {
            setState(1);

            $('.chat-state-4').hide();
            $('.chat-state-1').show();
        });
    }

    function maximizePopout() {
        setState(4);

        $('.chat-state-1').hide();
        $('.chat-state-4').show();

        scrollDown();
    }

    function updatePersonnalInformations(name, email) {
        $.ajax({
            type: "POST",
            url: "/?module=API&method=Chat.updatePersonnalInformations",
            dataType: "xml",
            cache: false,
            data: {visitorId: idvisitor, idSite: idsite, name: name, email: email},
            success: function (data) {
                console.log(data);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log("error: " + textStatus + " " + errorThrown);
            }
        });
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

    function setTranslationTable(translations) {
        __tr = translations;
    }

    function getState() {
        return localStorage.getItem('PopoutState');
    }

    function setState(state) {
        localStorage.setItem('PopoutState', state);
        socket.postMessage(state);
    }

    function getMessages() {
        return JSON.parse(localStorage.getItem('ChatMessages'));
    }

    function getLastReceivedMessage(){
        var messages = getMessages();
        return messages[messages.length - 1];
    }

    function getLastReceivedMessageMicrotime(){
        var lastMessage = getLastReceivedMessage();
        return lastMessage['microtime'];
    }

    function setMessages(messages) {
        localStorage.setItem('ChatMessages', JSON.stringify(messages));
    }

    function appendToMessages(messages) {
        if (messages.length == 0)
            return false;

        var actualMessages = getMessages();

        for (var i = 0, len = messages.length; i < len; i++) {
            actualMessages.push(messages[i]);
        }

        localStorage.setItem('ChatMessages', JSON.stringify(actualMessages));
    }

    function setSocket(easyXDMsocket) {
        socket = easyXDMsocket;
    }

    function setLanguage(lang){
        var splittedLang = lang.split(',');
        localLanguage = splittedLang[0];

        moment.locale(localLanguage);
    }

    function setIdvisitor(value) {
        if (idvisitor != null)
            return false;

        idvisitor = value;
    }

    function setIdsite(value) {
        if (idsite != null)
            return false;

        idsite = value;
    }

    return {
        initialize: function () {
            return initialize();
        },

        setTranslationTable: function (translations) {
            return setTranslationTable(translations);
        },

        setSocket: function (easyXDMsocket) {
            return setSocket(easyXDMsocket);
        },

        setIdvisitor: function (idvisitor) {
            return setIdvisitor(idvisitor);
        },

        setIdsite: function (idsite) {
            return setIdsite(idsite);
        },

        setLanguage: function (lang) {
            return setLanguage(lang);
        }
    }
}
