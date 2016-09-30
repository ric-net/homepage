/**
 * MicroEngine MailForm
 * http://microengine.jp/mailform/
 *
 * Copyright 2014, MicroEngine Inc. (http://microengine.jp)
 *
 * @copyright Copyright (C) 2014 MicroEngine Inc.
 * @version 1.0.0
 */
var memf = {};

(function ($) {

var lastZipcode = '';

memf.zipAddr = function (zip1, zip2, pref, city, town, st) {
    var zipcode = $('[name=' + zip1 + ']').val().replace(/-/, '');
    if (zip2) {
        zipcode += $('[name=' + zip2 + ']').val();
    }
    if (zipcode.length === 7 && lastZipcode !== zipcode) {
        $.ajax({
            type: 'GET',
            url: window.location.pathname + '?zipcode=' + zipcode,
            dataType: 'json',
            success: function (json) {
                if (!json) {
                    return;
                }
                var str = '';
                (function(obj){
                    for (var i in obj) {
                        if (json[i]) {
                            str = json[i] + str;
                        }
                        if (obj[i]) {
                            $('[name=' + obj[i] + ']').val(str);
                            str = '';
                        }
                    }
                })({st: st, town:town, city:city, pref:pref});
            }
        });
        lastZipcode = zipcode;
    }
};

})(jQuery);
