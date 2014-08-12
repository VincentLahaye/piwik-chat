var Piwik_Chat_Popout = (function ($) {

    var __tr,
        state,
        staffAlreadyAfk,
        lastNameStaff;

    function scrollDown() {
        var objDiv = document.getElementById("chat-conversation");
        objDiv.scrollTop = objDiv.scrollHeight;
    }

    function isStaffAFK() {
        var query = getQueryParams(document.location.search);

        if (state == 4) {
            var lastTimeMsgStaff = parseInt($(".author:not(:contains('" + __tr['You'] + "'))").last().parent().next().attr('data-microtime'));

            if (lastTimeMsgStaff < parseInt((Date.now() / 1000) - (2 * 60 * 60)) && !$("#chat-conversation p.has-quit").last().hasClass('offline')) {
                $('#chat-conversation').append('<p class="has-quit offline">' + $(".author:not(:contains('" + __tr['You'] + "'))").last().html() + ' has quit this session.</p>');
                staffAlreadyAfk = true;

                scrollDown();
            }
        }

        if (state != 1) {

            $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.isStaffOnline', idSite: query.idsite, format: 'json'},
                success: function (result) {
                    if (result.value == true) {
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

        setTimeout(Piwik_Chat_Popout.isStaffAFK(), 20000); // 20 seconds
    }

    function initialize(getState) {
        bindEventCallbacks();
        state = getState;
        scrollDown();
        isStaffAFK();
        poll();
    }

    function getQueryParams(qs) {
        qs = qs.split("+").join(" ");

        var params = {}, tokens,
            re = /[?&]?([^=]+)=([^&]*)/g;

        while (tokens = re.exec(qs)) {
            params[decodeURIComponent(tokens[1])]
                = decodeURIComponent(tokens[2]);
        }

        return params;
    }

    function appendMessage(user, message, microtime, date, time) {
        var lastAuthor = $('#chat-conversation .author').last().html();
        var html = "";
        var displayAuthor = false;

        if (user != __tr['You'] && (staffAlreadyAfk == true || $(".author:not(:contains('" + __tr['You'] + "'))").length == 0)) {
            $('#chat-conversation').append('<p class="has-quit">' + user + ' ' + __tr['HasJoin'] + '</p>');
            staffAlreadyAfk = false;
        }

        if (user != lastAuthor || $("#chat-conversation p").last().hasClass('has-quit') == true) {
            displayAuthor = true;
        }

        if (displayAuthor == true) {
            html += '<p class="author-container">';
            html += '<span class="author">' + user + '</span>';
            html += '<span class="microtime">';

            var lastDate = $("#chat-conversation p:not('.has-quit')").last().attr('data-date');
            var lastTime = $("#chat-conversation p:not('.has-quit')").last().attr('data-time');

            if (lastDate == date)
                var displayNone = ' style="display:none"';

            html += '<span class="date"' + displayNone + '>' + date + '</span>';

            if (lastTime == time)
                var displayNone = ' style="display:none"';

            html += '<span class="time"' + displayNone + '>' + time + '</span>';


            html += '</span>';
            html += '</p>';
        }

        html += '<p class="chat-msg" data-microtime="' + microtime + '" data-date="' + date + '" data-time="' + time + '">' + message + '</p>';

        $('#chat-conversation').append(html);

        if (user != __tr['You']) {
            lastNameStaff = user;
        }

        scrollDown();
    }

    function poll(microtime) {
        var query = getQueryParams(document.location.search),
            shouldWePlaySound = false;

        if (state == 1 || state == 4) {
            $.ajax({
                type: "GET",
                url: "/index.php",
                dataType: "json",
                cache: false,
                data: {module: 'API', method: 'Chat.poll', visitorId: $('#idvisitor').val(), idSite: query.idsite, format: 'json', microtime: microtime},
                success: function (data) {
                    console.log(data);

                    if (state != 4)
                        maximizePopout();

                    for (var i = 0, len = data.length; i < len; i++) {
                        appendMessage(data[i].answerfrom, data[i].content, data[i].microtime, data[i].date, data[i].time);

                        if (i == (len - 1))
                            var lastMicrotime = data[i].microtime;

                        shouldWePlaySound = true;
                    }

                    if (shouldWePlaySound) {
                        playSound('notification');
                    }

                    poll(lastMicrotime);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log("error: "+ textStatus + " "+ errorThrown);
                    setTimeout("Piwik_Chat_Popout.poll()", 15000);
                }
            });
        }
    }

    function sendMessage(textareaDomElement) {
        var query = getQueryParams(document.location.search);
        var message = $(textareaDomElement).val();
        var idVisitor = $('#idvisitor').val();

        if (message && idVisitor) {

            var offset = +1;
            var dateObj = new Date(new Date().getTime() + offset * 3600 * 1000);
            var hours = dateObj.getHours();
            var minutes = dateObj.getMinutes();
            var day = dateObj.getDate();
            var month = dateObj.getMonth() + 1;
            var year = dateObj.getFullYear();

            if (hours < 10)
                hours = "0" + hours;

            var time = hours + ":" + minutes;

            if (month < 10)
                month = "0" + month;

            var date = day + "/" + month + "/" + year;

            appendMessage(__tr['You'], message, (Date.now() / 1000), date, time);

            $(textareaDomElement).val("");

            $.ajax({
                type: "POST",
                url: "/?module=API&method=Chat.sendMessage&format=json",
                dataType: "json",
                cache: false,
                data: {visitorId: idVisitor, idSite: query.idsite, message: message},
                success: function (data) {
                    console.log(data);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log("error: " + textStatus + " " + errorThrown);
                },
                complete: function () {

                }
            });
        }
    }

    function submitMessageFromStep2(msg) {
        console.log('submitMessageFromStep2');

        $('.chat-state-3 .chat-input').html(msg);

        $('.chat-state-2').hide();
        $('.chat-state-3').show();

        $('.chat-state-3 .name').focus();

        state = 3;
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

            state = 4;
            poll();

            return false;
        });

        $('.chat-state-1 .action-logo').on('click', function (e) {
            maximizePopout();
        });

        $('.chat-state-4 .action-logo').on('click', function (e) {
            state = 1;

            $.get("/?module=API&method=Chat.setPopoutState&state=" + state);

            $('.chat-state-4').hide();
            $('.chat-state-1').show();
        });
    }

    function maximizePopout() {
        state = 4;

        $.get("/?module=API&method=Chat.setPopoutState&state=" + state);

        $('.chat-state-1').hide();
        $('.chat-state-4').show();
    }

    function updatePersonnalInformations(name, email) {
        var query = getQueryParams(document.location.search);
        var idVisitor = $('#idvisitor').val();

        $.ajax({
            type: "POST",
            url: "/?module=API&method=Chat.updatePersonnalInformations",
            dataType: "xml",
            cache: false,
            data: {visitorId: idVisitor, idSite: query.idsite, name: name, email: email},
            success: function (data) {
                console.log(data);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log("error: " + textStatus + " " + errorThrown);
            },
            complete: function () {

            }
        });
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

    function setTranslationTable(translations) {
        __tr = translations;
    }

    function getState() {
        return state;
    }

    return {
        getState: function () {
            return getState();
        },

        scrollDown: function () {
            return scrollDown();
        },

        isStaffAFK: function () {
            return isStaffAFK;
        },

        initialize: function (state) {
            return initialize(state);
        },

        poll: function () {
            return poll();
        },

        setTranslationTable: function (translations) {
            return setTranslationTable(translations);
        }
    }
})($);

socket = new easyXDM.Socket({
    remote: siteMainUrl,
    onReady: function () {
        var timer;
        if (!timer) {
            var currentState;
            timer = setInterval(function () {
                try {
                    if (Piwik_Chat_Popout.getState() != currentState) {
                        currentState = Piwik_Chat_Popout.getState();
                        socket.postMessage(currentState);
                    }
                } catch (e) {
                    // We tried to read the property at some point when it wasn't available
                }
            }, 100);
        }
    }
});