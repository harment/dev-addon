// src/addons/XBTTracker/js/xbt/slider.js
/**
 * XBT Torrent Slider
 */
var XBTSlider = XF.Element.newHandler({
    options: {
        itemsToShow: 5,
        autoplaySpeed: 5000,
        autoplay: true,
        responsive: true
    },
    
    /**
     * تهيئة السلايدر
     */
    init: function() {
        var self = this;
        
        if (typeof $.fn.slick === 'undefined') {
            console.error('Slick carousel library not found. Please include slick.js in your site.');
            return;
        }
        
        // تهيئة مكتبة Slick
        this.$target.slick({
            dots: true,
            infinite: true,
            speed: 500,
            slidesToShow: this.options.itemsToShow,
            slidesToScroll: 1,
            autoplay: this.options.autoplay,
            autoplaySpeed: this.options.autoplaySpeed,
            responsive: this.options.responsive ? [
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 4
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 3
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ] : []
        });
        
        // إعادة تهيئة Slick عند تغيير حجم النافذة
        $(window).on('resize', function() {
            self.$target.slick('refresh');
        });
    }
});

// تسجيل المعالج
XF.Element.register('xbt-slider', 'XBTSlider');