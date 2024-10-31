/**
 * Reload plugin
 * @author Bence Meszaros
 * @link http://bencemeszaros.com
 * @link http://wordpress.org/plugins/reload/
 * @version 1.1.3
 */

var ReloadPlugin = {

    interval: 60000,
    url: '',
    start: 0,
    errors: 0,
    errorLimit: 10,
    fastCheck: true,
    currentLinkElements: {},
    timer: 0,

    /** Initializes the start time and the query cicle */
    init: function() {
        // get the url for our php script (which is just beside this js file)
        ReloadPlugin.url = ReloadPlugin.scriptSource().replace(/\\/g, '/').replace(/\/[^\/]*\/?$/, '') + '/reload-monitor.php';

        if (0 == ReloadPlugin.start) {
            ReloadPlugin.start = new Date() * 1;
            ReloadPlugin.startTimer();
        }
    },

    startTimer: function() {
        ReloadPlugin.timer = setTimeout(ReloadPlugin.heartbeat, ReloadPlugin.interval);
    },

    setUrl: function(ajaxurl) {
        if (typeof ajaxurl != 'undefined' && ajaxurl != '') {
            ReloadPlugin.url = ajaxurl;
            ReloadPlugin.fastCheck = false;
        }
    },

    setInterval: function(interval) {
        if (typeof interval != 'undefined' && !isNaN(interval)) {
            ReloadPlugin.interval = interval;
            clearTimeout(ReloadPlugin.timer);
            ReloadPlugin.startTimer();
        }
    },

    /** Reload all local css files */
    reloadCss: function() {
        // helper method to check if a given url is local
        function isLocal(url) {
            var loc = document.location,
                reg = new RegExp("^\\.|^\/(?!\/)|^[\\w]((?!://).)*$|" + loc.protocol + "//" + loc.host);
            return url.match(reg);
        }

        var links = document.getElementsByTagName("link");

        for (var i = 0; i < links.length; i++) {
            var link = links[i], rel = link.getAttribute("rel"), href = link.getAttribute("href");
            if (href && rel && rel.match(new RegExp("stylesheet", "i")) && isLocal(href)) {
                // remove any url params
                var res = href.match(/(.*)\?.*/);
                href = res && res[1] ? res[1] : href;
                ReloadPlugin.currentLinkElements[href] = link;
            }
        }

        for (var url in ReloadPlugin.currentLinkElements) {
            var head = ReloadPlugin.currentLinkElements[url].parentNode,
                newLink = document.createElement("link"),
                oldLink = ReloadPlugin.currentLinkElements[url];

            newLink.setAttribute("href", url + "?timestamp=" + new Date() * 1);
            newLink.setAttribute("rel", "stylesheet");
            newLink.addEventListener('load', function() {
                setTimeout((function(node){return function() {
                    node.parentNode.removeChild(node);
                };})(oldLink), 100);
            }(oldLink), false);
            head.appendChild(newLink);
            ReloadPlugin.currentLinkElements[url] = newLink;
        }
    },

    scriptSource  : function(scripts) {
        var scripts = document.getElementsByTagName('script'),
            script = scripts[scripts.length - 1];

        if (script.getAttribute.length !== undefined) {
            return script.src
        }

        return script.getAttribute('src', -1);
    },

    /** performs a cycle per interval */
    heartbeat: function() {
        if (document.body) {
            ReloadPlugin.ask(ReloadPlugin.start);
        }
    },

    /** Queries the server for changes, and reloads the page on positive answer */
    ask: function(start) {
        var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XmlHttp");
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                var restart = new Date() * 1;
                if (xhr.responseText != '' && xhr.status == 200) {
                    var re = JSON.parse(xhr.responseText);

                    if (re === true) {
                        location.reload();
                        return true;
                    }
                    else {
                        // we got a number, meaning we should reload all local css files
                        restart = typeof re == "number" ? re : restart;
                        ReloadPlugin.reloadCss();
                    }
                }
                // Error, or no response at all
                else if (xhr.status >= 400 || xhr.status == 0) {
                    // After 10 errors we stop asking
                    ReloadPlugin.errors ++;
                    if (ReloadPlugin.errors >= ReloadPlugin.errorLimit) {
                        return false;
                    }
                }
                // reset the start time
                //ReloadPlugin.start = restart;
                ReloadPlugin.startTimer();
            }
        }
        xhr.open("GET", ReloadPlugin.url + '?action=reload_monitor&s=' + start, true);
        xhr.send();
    }

}
ReloadPlugin.init();