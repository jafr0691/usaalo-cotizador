/**
 * Polyfills para compatibilidad con navegadores antiguos
 * Archivo: assets/js/polyfills.js
 */

// =======================
// Polyfill: Object.assign
// =======================
if (typeof Object.assign !== 'function') {
    Object.assign = function(target, varArgs) {
        if (target == null) { // TypeError si undefined o null
            throw new TypeError('Cannot convert undefined or null to object');
        }
        var to = Object(target);

        for (var index = 1; index < arguments.length; index++) {
            var nextSource = arguments[index];
            if (nextSource != null) { // Evita undefined o null
                for (var nextKey in nextSource) {
                    if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
                        to[nextKey] = nextSource[nextKey];
                    }
                }
            }
        }
        return to;
    };
}

// =======================
// Polyfill: Array.from
// =======================
if (!Array.from) {
    Array.from = (function () {
        var toStr = Object.prototype.toString;
        var isCallable = function (fn) {
            return typeof fn === 'function' || toStr.call(fn) === '[object Function]';
        };
        var toInteger = function (value) {
            var number = Number(value);
            if (isNaN(number)) { return 0; }
            if (number === 0 || !isFinite(number)) { return number; }
            return (number > 0 ? 1 : -1) * Math.floor(Math.abs(number));
        };
        var maxSafeInteger = Math.pow(2, 53) - 1;
        var toLength = function (value) {
            var len = toInteger(value);
            return Math.min(Math.max(len, 0), maxSafeInteger);
        };

        return function from(arrayLike/*, mapFn, thisArg */) {
            var C = this;
            var items = Object(arrayLike);
            if (arrayLike == null) {
                throw new TypeError("Array.from requires an array-like object - not null or undefined");
            }
            var mapFn = arguments.length > 1 ? arguments[1] : void undefined;
            var T;
            if (typeof mapFn !== 'undefined') {
                if (!isCallable(mapFn)) {
                    throw new TypeError('Array.from: when provided, the second argument must be a function');
                }
                if (arguments.length > 2) {
                    T = arguments[2];
                }
            }
            var len = toLength(items.length);
            var A = isCallable(C) ? Object(new C(len)) : new Array(len);
            var k = 0;
            var kValue;
            while (k < len) {
                kValue = items[k];
                if (mapFn) {
                    A[k] = typeof T === 'undefined' ? mapFn(kValue, k) : mapFn.call(T, kValue, k);
                } else {
                    A[k] = kValue;
                }
                k += 1;
            }
            A.length = len;
            return A;
        };
    }());
}

// =======================
// Polyfill: Promise
// =======================
(function (global) {
    if (typeof global.Promise !== 'function') {
        // Cargar una implementación básica de Promise
        // Nota: Para proyectos reales, se recomienda usar "es6-promise" o "core-js"
        console.warn("Este navegador no soporta Promesas. Considera usar core-js o es6-promise.");
    }
})(this);

// =======================
// Polyfill: Fetch
// =======================
(function (self) {
    if (!self.fetch) {
        self.fetch = function (url, options) {
            return new Promise(function (resolve, reject) {
                var request = new XMLHttpRequest();
                request.open(options && options.method ? options.method : 'GET', url);

                for (var i in (options && options.headers || {})) {
                    request.setRequestHeader(i, options.headers[i]);
                }

                request.onload = function () {
                    resolve({
                        ok: (request.status / 100 | 0) === 2, // 200-299
                        status: request.status,
                        statusText: request.statusText,
                        text: function () { return Promise.resolve(request.responseText); },
                        json: function () { return Promise.resolve(JSON.parse(request.responseText)); }
                    });
                };

                request.onerror = reject;
                request.send(options && options.body ? options.body : null);
            });
        };
    }
})(typeof self !== 'undefined' ? self : this);

// =======================
// Polyfill: Element.closest
// =======================
if (!Element.prototype.closest) {
    Element.prototype.closest = function (s) {
        var el = this;
        do {
            if (el.matches(s)) return el;
            el = el.parentElement || el.parentNode;
        } while (el !== null && el.nodeType === 1);
        return null;
    };
}

// =======================
// Polyfill: Element.matches
// =======================
if (!Element.prototype.matches) {
    Element.prototype.matches = 
        Element.prototype.msMatchesSelector || 
        Element.prototype.webkitMatchesSelector;
}

console.log("Polyfills cargados correctamente.");
