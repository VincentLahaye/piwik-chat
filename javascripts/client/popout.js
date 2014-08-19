function Piwik_Chat_Popout() {

    var __tr,   // Translation table
        state,  // Popout state
        socket, // easyXDM socket
        conversation = [],
        staffAlreadyAfk = true,
        lastNameStaff,
        lastStaffMessage,
        localLanguage,
        idvisitor = null,
        idsite = null,
        domConversation = $('#chat-conversation');

    function initialize() {

        var messages = getMessages(),
            microtimeFrom = false,
            pollFromBeginning = false;

        if (messages != null && messages.length > 0) {
            microtimeFrom = getLastReceivedMessageMicrotime(); // Get last message microtime in order to get new message from this microtime
            populateConversation(getMessages(), {}, true);
            setState(4);
            //domConversation.append('<p style="margin:20px; text-align:center;">Loading new messages...</p>');
        } else {
            pollFromBeginning = true;
        }

        loadMessages({
            microtimeFrom: microtimeFrom,
            pollFromBeginning: pollFromBeginning,
            callback: function(){
                if ($.inArray(getState(), ['1', '2', '3', '4']) === -1) {
                    if (getMessages().length == 0) {
                        setState(2); // Need some help ?
                    } else {
                        setState(4); // Display conversation
                    }
                }
            }
        });

        bindEventCallbacks();
        isStaffAFK();
        poll();
    }

    function loadMessages(args){

        if(typeof args.pollFromBeginning === 'undefined')
            args.pollFromBeginning = false;

        $.ajax({
            type: "GET",
            url: "/index.php",
            dataType: "json",
            cache: false,
            data: {module: 'API', method: 'Chat.getMessages', idSite: idsite, visitorId: idvisitor, format: 'json', microtimeFrom: args.microtimeFrom},
            success: function (newMessages) {

                formatMessages(newMessages);

                if (args.pollFromBeginning === false) {
                    appendToMessages(newMessages);
                    populateConversation(newMessages, getLastReceivedMessage());
                } else {
                    setMessages(newMessages);
                    populateConversation(getMessages(), {}, true);
                }

                args.callback();
            }
        });
    }

    function populateConversation(messages, lastMessage, reInit){
        if(reInit == true)
            domConversation.html('');

        if(typeof lastMessage === 'undefined')
            lastMessage = {};

        if(messages.length > 0){ //typeof messages === 'array'){
            $.each(messages, function (key, message) {

                appendMessage(message, lastMessage, false);
                lastMessage = message;
            });
        }

        setState(4);
        scrollDown();
    }

    function poll(microtime) {
        $.ajax({
            type: "GET",
            url: "/index.php",
            dataType: "json",
            cache: false,
            data: {module: 'API', method: 'Chat.poll', visitorId: idvisitor, idSite: idsite, format: 'json', microtime: microtime},
            success: function (newMessages) {
                console.log("receive new message");
                console.log(newMessages);

                if(newMessages.value === false){
                    var microtime = getLastReceivedMessageMicrotime();
                } else {

                    formatMessages(newMessages);

                    var microtime = newMessages[newMessages.length - 1]['microtime'];

                    if (getState() != 4)
                        maximizePopout();

                    console.log(getLastReceivedMessage());

                    populateConversation(newMessages, getLastReceivedMessage());

                    appendToMessages(newMessages);

                    playSound('notification');
                }

                updateMoment();

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

    function appendMessage(message, lastMessage, loading) {

        if(loading == false && isMessageObjectValid(message) === false){
            console.log("Message invalid :");
            console.log(message);
            return;
        }

        var displayAuthor = false,
            currentAuthor = (message.answerfrom !== 'undefined' && message.answerfrom !== null) ? message.answerfrom : __tr['You'],
            lastAuthor = (lastMessage.answerfrom !== 'undefined' && lastMessage.answerfrom !== null) ? lastMessage.answerfrom : __tr['You'],
            classLoading = (loading == true) ? " loading" : "",
            html = "";

        /*if (staffAlreadyAfk == false && lastStaffMessage && lastStaffMessage.moment.isBefore(message.moment.subtract(2, 'h')) && lastMessage.idautomsg == null) {
            domConversation.append('<p class="has-join-or-quit">' + lastStaffMessage.answerfrom + ' ' + __tr['HasQuit'] + '</p>');
            staffAlreadyAfk = true;
            displayAuthor = true;
        }

        if (message.answerfrom && staffAlreadyAfk == true && (lastMessage !== 'undefined' && lastMessage.answerfrom === null) && message.idautomsg == null) {
            domConversation.append('<p class="has-join-or-quit" title="' + message.moment.format('LLL') + '">' + message.answerfrom + ' ' + __tr['HasJoin'] + '</p>');
            staffAlreadyAfk = false;
            displayAuthor = true;
        }*/

        if (currentAuthor != lastAuthor || $("#chat-conversation p").last().hasClass('has-join-or-quit') == true) {
            displayAuthor = true;
        }

        var fromNow = (loading != true && typeof message.moment !== 'undefined' && message.moment._isAMomentObject) ? message.moment.fromNow() : '';
        var msgMicrotime = (loading != true) ? message.microtime : '';
        var msgMoment = (loading != true) ? message.moment.format('LLL') : '';
        var idmsg = (loading != true) ? message.idmessage : '';

        if (displayAuthor == true) {
            html += '<p class="author-container'+ classLoading +'"><span class="author">' + currentAuthor + '</span><span class="microtime" data-timestamp="'+ msgMicrotime +'">' + fromNow + '</span></p>';
        }

        html += '<p class="chat-msg'+ classLoading +'" data-idmsg="' + idmsg + '" data-microtime="' + msgMicrotime + '" title="' + msgMoment + '">' + escape(message.content) + '<span class="microtime"></span></p>';

        if (message.answerfrom != null && message.idautomsg == null) {
            lastStaffMessage = message;
            console.log('last staff message:');
            console.log(lastStaffMessage);
        }

        domConversation.append(html);
        scrollDown();
    }

    function sendMessage(textareaDomElement) {
        var textareaVal = $(textareaDomElement).val();

        if (textareaVal != "") {
            var newMessage = {content: textareaVal, answerfrom: null, idmessage: null}

            console.log(newMessage);
            console.log(getLastReceivedMessage());

            appendMessage(newMessage, getLastReceivedMessage(), true);

            $(textareaDomElement).val("");

            $.ajax({
                type: "POST",
                url: "/?module=API&method=Chat.sendMessage&format=json",
                dataType: "json",
                cache: false,
                data: {visitorId: idvisitor, idSite: idsite, message: newMessage.content},
                success: function (message) {
                    newMessage.idmessage = message[0].idmessage;
                    newMessage.microtime = message[0].microtime;
                    newMessage.moment = moment.unix(message[0].microtime);

                    removeLoadingMessage(newMessage, function(){
                        return appendMessage(newMessage, getLastReceivedMessage(), false);
                    });

                    appendToMessages([newMessage]);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log("error: " + textStatus + " " + errorThrown);
                }
            });
        }
    }

    function removeLoadingMessage(message, callback){
        $('.author-container.loading').each(function(key, domElement){
            $(domElement).remove();
        });

        $('.chat-msg.loading').each(function(key, domElement){
            if($(domElement).text() == escape(message.content)){
                $(domElement).remove();
                callback();
                return;
            }
        })
    }

    function formatMessage(message){
        if(typeof message.microtime === 'undefined'){
            console.log("This message doesn't have a microtime property :");
            console.log(message);
        }

        if(typeof message.moment === 'undefined' || message.moment._isAMomentObject !== true){
            message.moment = moment(parseInt(message.microtime.replace('.', '').substr(0, 13)));
        }

        return message;
    }

    function formatMessages(messages){
        for(var i = 0, len = messages.length; i < len; i++){
            formatMessage(messages[i]);
        }

        return true;
    }

    function getLastReceivedMessage(){
        return conversation[conversation.length - 1];
    }

    function getLastReceivedMessageMicrotime(){
        var lastMessage = getLastReceivedMessage();
        return lastMessage['microtime'];
    }

    function getMessages() {
        if(typeof conversation === 'undefined'){
            conversation = JSON.parse(localStorage.getItem('ChatMessages'));

            if(conversation != null)
                formatMessages(conversation);
        }

        return conversation;
    }

    function setMessages(messages) {
        conversation = messages;
        localStorage.setItem('ChatMessages', JSON.stringify(messages));
    }

    function appendToMessages(messages) {
        /*if (typeof messages != 'object' && typeof messages != 'array')
            return false;*/

        //var actualMessages = getMessages();

        /*if(typeof messages == 'object'){
            console.log("Append one unique message");
            //if(isMessageObjectValid(messages))
            conversation.push(formatMessage(messages));
        } else if(typeof messages == 'array'){
*/
        console.log(typeof messages);
            for (var i = 0, len = messages.length; i < len; i++) {
                //if(isMessageObjectValid(messages[i]))
                conversation.push(formatMessage(messages[i]));
            }
        //}

        setMessages(conversation);
    }

    function isMessageObjectValid(message){
        return (typeof message.content != 'undefined' && message.content != '' &&
            typeof message.microtime != 'undefined' && message.microtime != '' &&
            typeof message.moment != 'undefined' && message.moment._isAMomentObject);
    }

    function isStaffAFK() {
        if(staffAlreadyAfk == false){
            if (getState() == 4) {
                var lastTimeMsgStaff = parseInt($(".author:not(:contains('" + __tr['You'] + "'))").last().parent().next().attr('data-microtime'));

                if (lastTimeMsgStaff < parseInt((Date.now() / 1000) - (2 * 60 * 60)) && !$("#chat-conversation p.has-join-or-quit").last().hasClass('offline')) {
                    domConversation.append('<p class="has-join-or-quit offline">' + $(".author:not(:contains('" + __tr['You'] + "'))").last().html() + ' has quit this session.</p>');
                    staffAlreadyAfk = true;

                    scrollDown();
                }
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

    function updateMoment(){
        $.each($('.microtime:visible'), function(key, domElement){
            domMoment = moment.unix($(domElement).attr('data-timestamp'));
            $(domElement).html(domMoment.fromNow());
        });
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
        $('.chat-state-' + state).show();

        if(state == '4')
            scrollDown();

        localStorage.setItem('PopoutState', state);
        socket.postMessage(state);
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

    function escape(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
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
        },
    }
}
