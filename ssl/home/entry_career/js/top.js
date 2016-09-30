jQuery(document).ready(function ($) {
    "use strict";

    var spW = 768;
    var pcW = 1400;
    var aniT = 1000;
    var intT = 5000;
    var imgH = 469;
    var topISize = $("#topImgNavi").find("li").length;
    var topIW = $("#topImg").width();
    var topIH = imgH;
    var topIUlW = topIW * topISize;
    var topILW = topIUlW * 3;
    var topILL = topIUlW * -1;
    var topIMT = 0;
    var topIML = 0;
    var winW = window.innerWidth ? window.innerWidth : $(window).width();

    function firstTop() {
        $("#topImgL ul").clone().prependTo("#topImgL").clone().prependTo("#topImgL");
    }

    function initTop() {
        winW = window.innerWidth ? window.innerWidth : $(window).width();
        if (winW > pcW) {
            topIW = winW;
            topIH = topIW / pcW * imgH;
            topIMT = (imgH - topIH) / 2;
            topIML = 0;
        } else if (winW > spW) {
            topIW = pcW;
            topIH = imgH;
            topIMT = 0;
            topIML = ($("#topImg").width() - topIW) / 2;
        } else {
            topIW = winW;
            topIH = topIW;
            topIMT = 0;
            topIML = 0;
        }
        topIUlW = topIW * topISize;
        topILW = topIUlW * 3;
        topILL = topIUlW * -1;
        if (winW > spW) {
            $("#topImg").height(imgH);
            aniT = 1000;
        } else {
            $("#topImg").height(winW);
            aniT = 500;
        }
        $("#topImgL ul li").width(topIW).height(topIH);
        $("#dummy_top_pc").css({position: "absolute", top: "0", left: "0", "z-index": "100"});
        $("#dummy_top_mobile").css({position: "absolute", top: "50px", left: "0", "z-index": "100"});
        $(".dummy_top").fadeOut(500, function () {
            $(this).hide();
        });
        $("#topImgL ul").width(topIUlW).height(topIH);
        $("#topImgL").width(topILW).height(topIH);
        $("#topImgL").css({"left": topILL, "margin-top": topIMT, "margin-left": topIML});
        $("#topImgNavi").find("li").removeClass("set");
        $("#topImgNavi").find("li:first-child").addClass("set");
    }

    $("#topImgP a").on("click", function () {
        var thisPlace = $("#topImgNavi li.set").index();
        if (thisPlace === 0) {
            $("#topImgNavi").find("li.set").removeClass("set");
            $("#topImgNavi").find("li:last-child").addClass("set");
        } else {
            $("#topImgNavi").find("li.set").removeClass("set").prev("li").addClass("set");
        }
        $("#topImgL").stop().animate({
                left: ((thisPlace - 1) * topIW * -1) + topILL
            }, aniT, "easeOutCirc", function () {
                if (thisPlace === 0) {
                    $("#topImgL").css("left", topIUlW * -2 + topIW);
                }
            }
        );
        return false;
    });

    $("#topImgN a").on("click", function () {
        var thisPlace = $("#topImgNavi li.set").index();
        if (thisPlace === topISize - 1) {
            $("#topImgNavi").find("li.set").removeClass("set");
            $("#topImgNavi").find("li:first-child").addClass("set");
        } else {
            $("#topImgNavi").find("li.set").removeClass("set").next("li").addClass("set");
        }
        $("#topImgL").stop().animate({
                left: ((thisPlace + 1) * topIW * -1) + topILL
            }, aniT, "easeOutCirc", function () {
                if (thisPlace === topISize - 1) {
                    $("#topImgL").css("left", topILL);
                }
            }
        );
        return false;
    });

    $("#topImgNavi li").on("click", function () {
        var nextPlace = $("#topImgNavi li").index(this);
        $("#topImgNavi").find("li.set").removeClass("set");
        $(this).addClass("set");
        $("#topImgL").stop().animate({
                left: nextPlace * topIW * -1 + topILL
            }, aniT, "easeOutCirc", function () {
            }
        );
        return false;
    });

    $("#topImgL").bind({
        "touchstart": function () {
            if (winW <= spW) {
                this.touchX = event.changedTouches[0].pageX;
                this.slideX = $(this).position().left;
            }
        }, "touchmove": function (e) {
            if (winW <= spW) {
                e.preventDefault();
                this.slideX = this.slideX - (this.touchX - event.changedTouches[0].pageX );
                $(this).css({left: this.slideX});
                this.accel = event.changedTouches[0].pageX - this.touchX;
                this.touchX = event.changedTouches[0].pageX;
            }
        }, "touchend": function () {
            if (winW <= spW) {
                if (this.accel > 0) {
                    $("#topImgP a").click();
                } else if (this.accel < 0) {
                    $("#topImgN a").click();
                }
            }
        }
    });

    $("#topImgL").mousedown(function () {
        if (winW <= spW) {
            this.touchX = event.pageX;
            this.slideX = $(this).position().left;
        }
        $("#topImgL").mousemove(function (e) {
            if (winW <= spW) {
                e.preventDefault();
                this.slideX = this.slideX - (this.touchX - event.pageX );
                $(this).css({left: this.slideX});
                this.accel = event.pageX - this.touchX;
                this.touchX = event.pageX;
            }
        });
    }).mouseup(function () {
        if (winW <= spW) {
            if (this.accel > 0) {
                $("#topImgP a").click();
            } else if (this.accel < 0) {
                $("#topImgN a").click();
            }
        }
        $("#topImgL").off("mousemove");
    });

    var timerID;
    var showMovie = false;

    function init() {
        if (!showMovie) {
            timerID = setInterval(function () {
                $("#topImgN a").click();
            }, intT);
        }
    }

    $("#topImgP a , #topImgN a, #topImgNavi li").click(function () {
        clearInterval(timerID);
        init();
    });

    $(window).resize(function () {
        initTop();
        clearInterval(timerID);
        init();
    });

    $(window).load(function () {
        setTimeout(function () {
            initInterval();
        }, 1500);
    });

    function initInterval() {
        firstTop();
        initTop();
        init();
    }

    $("#topImgL").hover(function () {
        clearInterval(timerID);
    }, function () {
        intT -= 2000;
        init();
        intT += 2000;
    });

    function initMovie() {
        var bodyW = $("body").width();
        var bodyH = window.innerHeight;
        var popMovT = Math.round((bodyH - 410) / 2);
        $("#popMovIn,#bgPop").width(bodyW).height(bodyH);
    }

    $("#topImgL").on('click', 'a.topMovieBlock', function () {
        showMovie = true;
        clearInterval(timerID);
        $("#popMovSec iframe").attr("src", $(this).attr("href"));
        $("#popMov").fadeIn();
        initMovie();
        return false;
    });

    $("#popMovSec > a,#bgPop").click(function () {
        showMovie = false;
        $("#popMov").fadeOut();
        $("#popMovSec iframe").attr("src", "");
        init();
        return false;
    });

    $(window).resize(function () {
        initMovie();
    });

    $(window).load(function () {
        initMovie();
    });
});
