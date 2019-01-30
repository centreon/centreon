"use strict";

Object.defineProperty(exports, "__esModule", {
    value: true
});

var _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; };

exports.default = function (data, dispatch, requestType) {
    return new Promise(function (resolve, reject) {
        dispatch(_extends({
            type: "@axios/" + requestType + "_DATA"
        }, data, {
            resolve: resolve,
            reject: reject
        }));
    });
};