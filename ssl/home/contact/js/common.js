jQuery(document).ready(function ($) {
	"use strict";
	
	var spW = 768;
	var winW = window.innerWidth ? window.innerWidth: $(window).width();
	
	function initMenu(){
		winW = window.innerWidth ? window.innerWidth: $(window).width();
		if(winW > spW) {
			var subML = ($("header").width() - $("#headerIn").width()) / 2 * -1;
			$(".subMenu").width($("header").width()).css("left", subML);
			$("#hdMenu").show();
			$(".spNav dl dd").removeAttr("style");
			$(".spNav dl dt").removeClass();
		} else {
			$(".subMenu").removeAttr("style");
		}
	}
	
	$("#navPc > ul > li").hover(
		function(){
			$("#hdMenu nav > ul > li").removeClass("set");
			$(".subMenu").hide();
			$(this).addClass("set");
			$(this).find(".subMenu").stop().fadeIn(1000);
		},
		function(){
			$(this).removeClass("set");
			$(this).find(".subMenu").stop().fadeOut(500);
		}
	);
	
	$("#pagetop a").on("click", function(){
		$("html , body").animate({ scrollTop: $("body").offset().top}, "normal");
		return false;
	});
	
	$("#hdBtM a").on("click", function(){
		$("#hdBtM").hide();
		$("#hdBtC").show();
		$("#hdMenu").stop().fadeIn();
		return false;
	});
	
	$("#hdBtC a").on("click", function(){
		$("#hdBtM").show();
		$("#hdBtC").hide();
		$("#hdMenu").stop().fadeOut();
		return false;
	});
	
	$(".spNav dl dt").on("click", function(){
		if(winW <= spW) {
			if($(this).hasClass("open")) {
				$(this).removeClass("open").addClass("close");
				$(this).next("dd").slideUp("fast");
			} else {
				$(this).removeClass("close").addClass("open");
				$(this).next("dd").slideDown("slow");
			}
			return false;
		}
	});
	
	$(window).scroll(function(){
/*
		if ($(window).scrollTop() > ($("footer").offset().top - $(window).height())) {
			$("#pagetop").addClass("nonfixed");
		} else {
			$("#pagetop").removeClass("nonfixed");
		}
*/
		if ($(window).scrollTop() > 40) {
			$("header").addClass("hdfixed");
		} else {
			$("header").removeClass("hdfixed");
		}
		if ($(window).scrollTop() === 0) {
			$("#pagetop").fadeOut();
		} else {
			$("#pagetop").fadeIn();
		}
	});
	
	$(window).resize(function(){
		initMenu();
	});
	
	$(window).load(function(){
		initMenu();
	});

	if ($('#topNews > div > ul')[0]) {
		$('#topNews > div > ul').newsTicker({
			row_height: 60,
			max_rows: 1,
			duration: 5000,
			pauseOnHover: 1
		});
	}
});
