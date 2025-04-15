// src/addons/XBTTracker/js/xbt/tmdb.js
/**
 * XBT TMDB Integration
 */
var XBTTmdb = XF.Element.newHandler({
    options: {
        searchUrl: null,
        detailsUrl: null,
        type: 'movie'
    },
    
    /**
     * تهيئة 
     */
    init: function() {
        this.$search = this.$target.find('.js-tmdbSearch');
        this.$results = this.$target.find('.js-tmdbResults');
        this.$loading = this.$target.find('.js-tmdbLoading');
        this.$search.on('keydown', XF.proxy(this, 'onSearchKeydown'));
        this.$target.on('click', '.js-tmdbSearchButton', XF.proxy(this, 'onSearchClick'));
        this.$target.on('click', '.js-tmdbSelectItem', XF.proxy(this, 'onSelectItem'));
        this.$target.on('click', '.js-tmdbLoadMore', XF.proxy(this, 'loadMore'));
        
        this.currentPage = 1;
        this.totalPages = 1;
        this.lastQuery = '';
    },
    
    /**
     * التعامل مع النقر على زر البحث
     */
    onSearchClick: function(e) {
        e.preventDefault();
        this.search();
    },
    
    /**
     * التعامل مع ضغط المفاتيح في حقل البحث
     */
    onSearchKeydown: function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.search();
        }
    },
    
    /**
     * إجراء بحث في TMDB
     */
    search: function() {
        var query = this.$search.val().trim();
        if (!query || query === this.lastQuery) {
            return;
        }
        
        this.lastQuery = query;
        this.currentPage = 1;
        
        this.$loading.removeClass('is-hidden');
        this.$results.addClass('is-hidden');
        
        XF.ajax('get', this.options.searchUrl, {
            query: query,
            type: this.options.type,
            page: 1
        }, XF.proxy(this, 'onSearchResponse'));
    },
    
    /**
     * التعامل مع استجابة طلب البحث
     */
    onSearchResponse: function(data) {
        this.$loading.addClass('is-hidden');
        this.$results.removeClass('is-hidden');
        
        if (data.html) {
            this.$results.html(data.html);
            
            // تعيين معلومات الصفحات
            if (data.pagination) {
                this.currentPage = data.pagination.current;
                this.totalPages = data.pagination.last;
                
                // إظهار/إخفاء زر "تحميل المزيد"
                if (this.currentPage < this.totalPages) {
                    this.$target.find('.js-tmdbLoadMore').removeClass('is-hidden');
                } else {
                    this.$target.find('.js-tmdbLoadMore').addClass('is-hidden');
                }
            }
        } else {
            this.$results.html('<div class="blockMessage">' + XF.phrase('xbt_no_results_found') + '</div>');
        }
    },
    
    /**
     * تحميل المزيد من النتائج
     */
    loadMore: function() {
        if (this.currentPage >= this.totalPages) {
            return;
        }
        
        var nextPage = this.currentPage + 1;
        
        this.$loading.removeClass('is-hidden');
        
        XF.ajax('get', this.options.searchUrl, {
            query: this.lastQuery,
            type: this.options.type,
            page: nextPage
        }, XF.proxy(this, 'onLoadMoreResponse'));
    },
    
    /**
     * التعامل مع استجابة طلب تحميل المزيد
     */
    onLoadMoreResponse: function(data) {
        this.$loading.addClass('is-hidden');
        
        if (data.html) {
            // إضافة المزيد من النتائج للقائمة الحالية
            var $newResults = $(data.html).find('.js-tmdbItem');
            this.$results.find('.js-tmdbResults').append($newResults);
            
            // تحديث معلومات الصفحات
            if (data.pagination) {
                this.currentPage = data.pagination.current;
                this.totalPages = data.pagination.last;
                
                // إخفاء زر "تحميل المزيد" إذا وصلنا للصفحة الأخيرة
                if (this.currentPage >= this.totalPages) {
                    this.$target.find('.js-tmdbLoadMore').addClass('is-hidden');
                }
            }
        }
    },
    
    /**
     * اختيار عنصر TMDB
     */
    onSelectItem: function(e) {
        e.preventDefault();
        
        var $item = $(e.currentTarget).closest('.js-tmdbItem');
        var tmdbId = $item.data('id');
        var tmdbTitle = $item.data('title');
        
        // إرسال البيانات إلى النافذة الأم
        window.opener.postMessage({
            tmdbId: tmdbId,
            tmdbTitle: tmdbTitle,
            tmdbType: this.options.type
        }, window.location.origin);
        
        // إغلاق النافذة المنبثقة
        window.close();
    }
});

// تسجيل المعالج
XF.Element.register('xbt-tmdb', 'XBTTmdb');

