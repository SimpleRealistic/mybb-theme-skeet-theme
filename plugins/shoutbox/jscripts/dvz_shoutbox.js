/* DVZ Shoutbox by Tomasz 'Devilshakerz' Mlynski [devilshakerz.com]; Copyright (C) 2014-2016 */

var dvz_shoutbox = {

    // defaults
    interval:   5,
    antiflood:  0,
    maxShouts:  20,
    awayTime:   600000,
    lazyMode:   false,
    lazyMargin: 0,
    callSign:   '@',
    lang:       [],
    status:     true,
    reversed:   false,
    markUnread: false,
    callbacks:  {
        'toggle':  [],
        'update':  [],
        'recall':  [],
        'entries': [],
        'shout':   [],
        'edit':    [],
        'delete':  [],
        'call':    [],
    },

    // runtime
    recalling:   false,
    timeout:     false,
    holdTimeout: false,
    frozen:      false,
    updating:    false,
    started:     false,
    lastSent:    0,
    firstId:     0,
    lastId:      0,
    activity:    0,

    loop: function(forced) {

        if (forced == true) {
            clearTimeout(dvz_shoutbox.timeout);
        } else {

            if (dvz_shoutbox.isAway()) {
                dvz_shoutbox.toggle(false, false);
                dvz_shoutbox.frozen = true;
                return false;
            }

            if (!dvz_shoutbox.lazyLoad()) {
                dvz_shoutbox.frozen = true;
                return false;
            }

            if (dvz_shoutbox.status == false) {
                dvz_shoutbox.frozen = true;
                return false;
            }

        }

        dvz_shoutbox.update(function() {

            dvz_shoutbox.started = true;

            // next request
            if (dvz_shoutbox.interval) {
                dvz_shoutbox.timeout = setTimeout(dvz_shoutbox.loop, dvz_shoutbox.interval * 1000);
            }

        });

    },

    // actions
    update: function(callback) {

        if (dvz_shoutbox.updating) {
            return false;
        } else {
            dvz_shoutbox.updating = true;
        }

        $.get(
            'xmlhttp.php',
            { action: 'dvz_sb_get_updates', first: dvz_shoutbox.firstId, last: dvz_shoutbox.lastId },
            function(data) {

                if (dvz_shoutbox.handleErrors(data)) {
                    return false;
                }

                if (data) {

                    var data = $.parseJSON(data);

                    // new shouts
                    if (data.html) {

                        // insert new shouts
                        if (dvz_shoutbox.reversed) {

                            var scrollMax = $('#shoutbox .data').innerHeight() - $('#shoutbox .window').innerHeight(),
                                scroll    = $('#shoutbox .window').scrollTop();

                            $('#shoutbox .data').append( $(data.html).fadeIn(function() {

                                // scroll to bottom again
                                if (!dvz_shoutbox.started || scroll >= scrollMax) {
                                    $('#shoutbox .window').scrollTop( $('#shoutbox .window')[0].scrollHeight );
                                }

                            }) );

                        } else {
                            $('#shoutbox .data').prepend( $(data.html).hide().fadeIn() );
                        }

                        // remove old shouts to fit the limit
                        var old = $('#shoutbox .entry').length - dvz_shoutbox.maxShouts;

                        if (old > 0) {
                            $('#shoutbox .entry:nth'+(dvz_shoutbox.reversed ? '' : '-last')+'-child(-n+'+old+')').remove();
                            dvz_shoutbox.firstId = $('#shoutbox .entry:'+(dvz_shoutbox.reversed ? 'first' : 'last')+'-child').attr('data-id');
                        }

                        // mark new shouts
                        if (dvz_shoutbox.started) {

                            $('#shoutbox .entry').filter(function() {
                                return parseInt($(this).attr('data-id')) > dvz_shoutbox.lastId && $(this).not('[data-own]').length;
                            }).addClass('new');

                            setTimeout("$('#shoutbox .entry.new').removeClass('new')", 1000);
                        }

                        dvz_shoutbox.lastId = data.last;

                        if (dvz_shoutbox.firstId == 0 && data.first !== undefined) {
                            dvz_shoutbox.firstId = data.first;
                        }

                        dvz_shoutbox.parseEntries(true);
                        dvz_shoutbox.updateLastRead();

                    }

                    // sync updates
                    if (data.sync) {

                        for (var i in data.sync) {

                            var entry = $('#shoutbox .entry[data-id='+i+']');

                            if (data.sync[i] === null) {
                                entry.fadeOut(function() {
                                    $(this).remove();
                                });
                            } else {
                                entry.children('.text').html(data.sync[i]);
                            }

                        }

                    }

                }

                dvz_shoutbox.updating = false;

                dvz_shoutbox.runCallbacks('update');

                if (typeof(callback) == 'function') {
                    callback();
                }

            }
        );

    },

    recall: function() {

        $.get(
            'xmlhttp.php',
            { action: 'dvz_sb_recall', first: dvz_shoutbox.firstId },
            function(data) {

                if (dvz_shoutbox.handleErrors(data)) {
                    return false;
                }

                if (data) {

                    var data = $.parseJSON(data);

                    // insert new shouts
                    if (dvz_shoutbox.reversed) {

                        var heightBefore = $('#shoutbox .data').height();

                        $('#shoutbox .data').prepend( $(data.html) );

                        var heightAfter = $('#shoutbox .data').height();

                        if ($('#shoutbox .window').scrollTop() == 0) {
                            $('#shoutbox .window').scrollTop(heightAfter - heightBefore);
                        }

                    } else {
                        $('#shoutbox .data').append( $(data.html) );
                    }

                    // extend the limit
                    dvz_shoutbox.maxShouts = $('#shoutbox .entry').length;

                    dvz_shoutbox.firstId = data.first;

                    dvz_shoutbox.parseEntries();
                    dvz_shoutbox.updateLastRead();

                    if (data.end) {
                        dvz_shoutbox.recalling = false;
                    }

                }

                dvz_shoutbox.runCallbacks('recall');

            }
        );

    },

    shout: function() {

        var message = $('#shoutbox input.text').val();

        if ($.trim(message) == '') {
            return false;
        }

        if (!dvz_shoutbox.antifloodPass()) {
            dvz_shoutbox.handleErrors('A');
            return false;
        }

        dvz_shoutbox.toggleForm(false);

        $.post(
            'xmlhttp.php',
            { action: 'dvz_sb_shout', text: message, key: my_post_key },
            function(data) {

                if (!dvz_shoutbox.handleErrors(data)) {

                    dvz_shoutbox.lastSent = Math.floor((new Date).getTime() / 1000);
                    dvz_shoutbox.clearForm();
                    dvz_shoutbox.loop(true);

                    dvz_shoutbox.runCallbacks('shout', { message: message });

                }

                dvz_shoutbox.toggleForm(true);

            }
        );

    },

    edit: function(id) {

        // text request
        $.get(
            'xmlhttp.php',
            { action: 'dvz_sb_get', id: id, key: my_post_key },
            function(data) {

                if (dvz_shoutbox.handleErrors(data)) {
                    return false;
                }

                var data    = $.parseJSON(data),
                    newText = prompt('Shout #'+id+':', data.text);

                if (newText && newText != data.text) {

                    // update request
                    $.post(
                        'xmlhttp.php',
                        { action: 'dvz_sb_update', text: newText, id: id, key: my_post_key },
                        function(data) {

                            if (!dvz_shoutbox.handleErrors(data)) {

                                $('#shoutbox .entry[data-id="'+id+'"] .text').html(data);

                                dvz_shoutbox.runCallbacks('edit', { id: id, text: data });

                            }

                        }
                    );

                }

            }
        );
    },

    delete: function(id, noConfirm) {

        if (noConfirm || confirm(dvz_shoutbox.lang[0])) {

            $.post(
                'xmlhttp.php',
                { action: 'dvz_sb_delete', id: id, key: my_post_key },
                function(data) {

                    if (!dvz_shoutbox.handleErrors(data)) {

                        $('#shoutbox .entry[data-id="'+id+'"]').fadeOut(function() { $(this).remove() });

                        dvz_shoutbox.runCallbacks('delete', { id: id });

                    }

                }
            );

        }

    },

    // functionality
    toggle: function(status, remember) {

        if (status == true) {

            dvz_shoutbox.status = true;

            $('#shoutbox').removeClass('collapsed');
            $('#shoutbox .body').fadeIn();

            if (dvz_shoutbox.frozen || !dvz_shoutbox.started) {
                dvz_shoutbox.frozen = false;
                dvz_shoutbox.loop();
            }

        } else {

            dvz_shoutbox.status = false;

            $('#shoutbox .body').stop(1).fadeOut(function() {
                if (dvz_shoutbox.status == false) $('#shoutbox').stop(1).addClass('collapsed');
            });

        }

        if (remember !== false) {
            Cookie.set('dvz_sb_status', status ? '1' : '0');
        }

        dvz_shoutbox.runCallbacks('toggle', { status: status });

    },

    // core
    antifloodPass: function() {
        var time = Math.floor((new Date).getTime() / 1000);
        return (time - dvz_shoutbox.lastSent) >= dvz_shoutbox.antiflood;
    },

    updateActivity: function() {
        dvz_shoutbox.activity = (new Date).getTime();
    },

    isAway: function() {
        if (!dvz_shoutbox.awayTime) return false;
        return (new Date).getTime() - dvz_shoutbox.activity > dvz_shoutbox.awayTime;
    },

    onDisplay: function() {
        var viewTop       = $(document).scrollTop(),
            viewBottom    = viewTop + $(window).height(),
            elementTop    = $('#shoutbox').offset().top,
            elementBottom = elementTop + $('#shoutbox').height();

        return elementBottom >= (viewTop - dvz_shoutbox.lazyMargin) && elementTop <= (viewBottom + dvz_shoutbox.lazyMargin);
    },

    checkVisibility: function() {
        if (dvz_shoutbox.frozen && dvz_shoutbox.onDisplay()) {
            dvz_shoutbox.frozen = false;
            dvz_shoutbox.loop();
        }
    },

    lazyLoad: function() {
        if (dvz_shoutbox.lazyMode && !dvz_shoutbox.onDisplay()) {
            if (
                dvz_shoutbox.lazyMode == 'start' && !dvz_shoutbox.started ||
                dvz_shoutbox.lazyMode == 'always'
            ) {
                return false;
            }
        }

        return true;
    },

    handleErrors: function(response) {
        if (response == 'A') {
            alert(dvz_shoutbox.lang[1]);
            return true;
        } else
        if (response == 'P') {
            alert(dvz_shoutbox.lang[2]);
            return true;
        }
        if (response == 'S') {
            dvz_shoutbox.toggle(false);
            return true;
        }

        return false;
    },

    runCallbacks: function(name, data) {
        if (dvz_shoutbox.callbacks[name]) {
            for (var i in dvz_shoutbox.callbacks[name]) {
                dvz_shoutbox.callbacks[name][i](data);
            }
        }
    },

    // visual
    call: function(username) {

        var $input = $('#shoutbox input.text'),
            words = $input.val().split(' '),
            appendix = username;

        // enclose in quotes if needed
        if (username.match( /["'`\.:\-+=~@#$%^*!?()\[\]{}\s]+/g )) {

            var quotes = ['"', "'", '`'];

            for (var i in quotes) {
                if (username.indexOf(quotes[i]) == -1) {
                    appendix = quotes[i] + username + quotes[i];
                    break;
                }
            }

        }

        // add a call sign
        appendix = dvz_shoutbox.callSign + appendix;

        // add a leading space if suitable
        if ($input.val() != '' && $input.val().slice(-1) != ' ') {
            appendix = ' ' + appendix;
        }

        // add a trailing space if suitable
        for (var i in words) {
            if (words[i] != '' && words[i].slice(0,1) != dvz_shoutbox.callSign) {
                break;
            }
            if (i == words.length-1) {
                appendix = appendix + ' ';
            }
        }

        $('#shoutbox input.text').focus();
        $('#shoutbox input.text').val($input.val() + appendix);
        $('#shoutbox input.text').focus();

        dvz_shoutbox.runCallbacks('call', { username: username });

    },

    toggleForm: function(status) {
        if (status == false) {
            $("#shoutbox input.text").attr('disabled', 'disabled');
        } else {
            $("#shoutbox input.text").removeAttr('disabled');
            $("#shoutbox input.text").focus();
        }
    },

    clearForm: function() {
        $('#shoutbox input.text').val('');
    },

    parseEntries: function(areLatest) {
        dvz_shoutbox.runCallbacks('entries');

        $('#shoutbox .entry:not([data-parsed])').each(function() {

            if (typeof $(this).attr('data-mod') !== 'undefined') {
                $(this).children('.info').prepend('<a href="" class="mod edit">E</a><a href="" class="mod del">X</a>');
            }

            if (dvz_shoutbox.markUnread) {
                if ((areLatest === true ? dvz_shoutbox.firstId : $(this).attr('data-id')) > parseInt(Cookie.get('dvz_sb_last_read'))) {
                    $(this).addClass('unread');
                }
            }

            $(this).attr('data-parsed', '');

        });
    },

    updateLastRead: function() {
        if (dvz_shoutbox.markUnread) {
            if (
                Cookie.get('dvz_sb_last_read') === undefined ||
                (dvz_shoutbox.firstId <= Cookie.get('dvz_sb_last_read') && Cookie.get('dvz_sb_last_read') != dvz_shoutbox.lastId))
            {
                Cookie.set('dvz_sb_last_read', dvz_shoutbox.lastId);
            }
        }
    },

};

$(document).on('click', '#shoutbox .head', function() {
    dvz_shoutbox.toggle(!dvz_shoutbox.status);
});
$(document).on('click', '#shoutbox .head a', function(e) {
    e.stopPropagation();
});
$(document).on('click', '#shoutbox .entry .avatar', function() {
    if ($('#shoutbox input.text').length) {
        dvz_shoutbox.call( $(this).parents('.entry').attr('data-username') );
    }
    return false;
});
$(document).on('click', '#shoutbox .entry .mod.edit', function() {
    dvz_shoutbox.edit( $(this).parents('.entry').attr('data-id') );
    return false;
});
$(document).on('mousedown', '#shoutbox .entry[data-mod] .text', function() {
    dvz_shoutbox.holdTimeout = setTimeout($.proxy(function() {
        dvz_shoutbox.edit( $(this).parents('.entry').attr('data-id') );
    }, this), 500);

}).bind('mouseup mouseleave mousemove', function() {
    clearTimeout(dvz_shoutbox.holdTimeout);
});
$(document).on('click', function(e) {
    if (e.which == 2 && $(e.target).is('#shoutbox .entry .mod.del')) {
        dvz_shoutbox.delete( $(e.target).parents('.entry').attr('data-id'), true );
        e.preventDefault();
    }
});

$(document).on('click', '#shoutbox .entry .mod.del', function() {
    dvz_shoutbox.delete( $(this).parents('.entry').attr('data-id') );
    return false;
});
$('#shoutbox .window').scroll(function() {
    if (dvz_shoutbox.recalling && $('#shoutbox .entry').length == dvz_shoutbox.maxShouts) {

        var scrollMax = $('#shoutbox .data').innerHeight() - $('#shoutbox .window').innerHeight(),
            scroll    = $('#shoutbox .window').scrollTop();

        if (
            !dvz_shoutbox.reversed && scroll >= scrollMax ||
            dvz_shoutbox.reversed && scroll == 0
        ) {
            dvz_shoutbox.recall();
        }
    }
});
$(document).on('submit', '#shoutbox .panel form', function() {
    dvz_shoutbox.shout();
    return false;
});

$(function(){
    if (dvz_shoutbox.reversed) {
        $('#shoutbox .window').scrollTop( $('#shoutbox .window')[0].scrollHeight );
    } else {
        $('#shoutbox .window').scrollTop(0);
    }
});