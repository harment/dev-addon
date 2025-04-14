// src/addons/XBTTracker/js/xbt/torrent-list.js
/**
 * XBT Torrent List
 */
var XBTTorrentList = XF.Element.newHandler({
    options: {
        categorySelector: 'select[name="category_id"]',
        searchSelector: 'input[name="search"]',
        qualitySelector: 'select[name="quality"]',
        audioSelector: 'select[name="audio"]',
        channelsSelector: 'select[name="channels"]',
        statusSelector: 'select[name="status"]',
        sortSelector: 'select[name="sort"]',
        orderSelector: 'select[name="order"]',
        filterUrl: null
    },
    
    /**
     * تهيئة 
     */
    init: function() {
        this.$category = this.$target.find(this.options.categorySelector);
        this.$search = this.$target.find(this.options.searchSelector);
        this.$quality = this.$target.find(this.options.qualitySelector);
        this.$audio = this.$target.find(this.options.audioSelector);
        this.$channels = this.$target.find(this.options.channelsSelector);
        this.$status = this.$target.find(this.options.statusSelector);
        this.$sort = this.$target.find(this.options.sortSelector);
        this.$order = this.$target.find(this.options.orderSelector);
        
        // الاستماع للتغييرات
        this.$category.on('change', XF.proxy(this, 'onFilterChange'));
        this.$quality.on('change', XF.proxy(this, 'onFilterChange'));
        this.$audio.on('change', XF.proxy(this, 'onFilterChange'));
        this.$channels.on('change', XF.proxy(this, 'onFilterChange'));
        this.$status.on('change', XF.proxy(this, 'onFilterChange'));
        this.$sort.on('change', XF.proxy(this, 'onFilterChange'));
        this.$order.on('change', XF.proxy(this, 'onFilterChange'));
        
        // البحث بعد تأخير كتابة
        this.$search.on('input', XF.proxy(this, 'onSearchInput'));
        this.searchTimer = null;
    },
    
    /**
     * تغيير المرشحات
     */
    onFilterChange: function() {
        this.applyFilters();
    },
    
    /**
     * إدخال نص البحث
     */
    onSearchInput: function() {
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
        
        this.searchTimer = setTimeout(XF.proxy(this, 'applyFilters'), 500);
    },
    
    /**
     * تطبيق المرشحات وتحديث القائمة
     */
    applyFilters: function() {
        var params = {
            category_id: this.$category.val(),
            search: this.$search.val(),
            quality: this.$quality.val(),
            audio: this.$audio.val(),
            channels: this.$channels.val(),
            status: this.$status.val(),
            sort: this.$sort.val(),
            order: this.$order.val()
        };
        
        // إزالة المعلمات الفارغة
        Object.keys(params).forEach(function(key) {
            if (params[key] === '' || params[key] === null) {
                delete params[key];
            }
        });
        
        // الانتقال إلى الصفحة مع المرشحات
        XF.redirect(this.options.filterUrl, params);
    }
});

// تسجيل المعالج
XF.Element.register('xbt-torrent-list', 'XBTTorrentList');

