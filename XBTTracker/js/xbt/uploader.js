// src/addons/XBTTracker/js/xbt/uploader.js
/**
 * XBT Torrent Uploader
 */
var XBTUploader = XF.Element.newHandler({
    options: {
        tmdbId: null,
        tmdbInputSelector: 'input[name="tmdb_id"]',
        tmdbTitleSelector: 'input[name="title"]'
    },
    
    /**
     * تهيئة المحمل
     */
    init: function() {
        // استمع لرسائل من إطار البحث في TMDB
        window.addEventListener('message', XF.proxy(this, 'receiveTmdbData'));
        
        // استمع لتغيير ملف التورنت
        var $torrentFile = this.$target.find('input[name="torrent_file"]');
        if ($torrentFile.length) {
            $torrentFile.on('change', XF.proxy(this, 'onTorrentFileChange'));
        }
    },
    
    /**
     * استقبال بيانات TMDB من إطار البحث
     */
    receiveTmdbData: function(event) {
        if (event.origin !== window.location.origin) {
            return;
        }
        
        if (event.data && event.data.tmdbId) {
            var tmdbId = event.data.tmdbId;
            var tmdbTitle = event.data.tmdbTitle || '';
            
            // تعيين قيمة معرف TMDB
            this.$target.find(this.options.tmdbInputSelector).val(tmdbId);
            
            // إذا كان العنوان فارغًا، استخدم عنوان TMDB
            var $titleInput = this.$target.find(this.options.tmdbTitleSelector);
            if (!$titleInput.val() && tmdbTitle) {
                $titleInput.val(tmdbTitle);
            }
            
            // إعلام المستخدم
            XF.flashMessage(XF.phrase('xbt_tmdb_data_selected'), 2000);
        }
    },
    
    /**
     * تغيير ملف التورنت
     */
    onTorrentFileChange: function(e) {
        var file = e.target.files[0];
        if (!file) {
            return;
        }
        
        // التحقق من نوع الملف
        if (file.name.substr(-8) !== '.torrent') {
            XF.alert(XF.phrase('xbt_invalid_torrent_file_extension'));
            e.target.value = '';
            return;
        }
        
        // استخراج اسم الملف كعنوان افتراضي إذا كان حقل العنوان فارغًا
        var $titleInput = this.$target.find(this.options.tmdbTitleSelector);
        if (!$titleInput.val()) {
            var fileNameWithoutExt = file.name.replace(/\.torrent$/, '');
            $titleInput.val(this.cleanTorrentFileName(fileNameWithoutExt));
        }
    },
    
    /**
     * تنظيف اسم ملف التورنت من أحرف خاصة
     */
    cleanTorrentFileName: function(fileName) {
        // استبدال الشرطات والنقاط بمسافات
        fileName = fileName.replace(/[._-]/g, ' ');
        
        // إزالة تنسيقات الجودة الشائعة
        fileName = fileName.replace(/\b(1080p|720p|4K|UHD|BluRay|BRRip|WEBRip|HDRip|DVDRip|XviD|x264|x265|HEVC|AAC|MP3|DTS|AC3)\b/gi, '');
        
        // إزالة أسماء فرق الإصدار
        fileName = fileName.replace(/\b(YIFY|YTS|RARBG|EZTV|ETTV|FGT|MkvCage|PSA|SPARKS|GECKOS)\b/gi, '');
        
        // إزالة المسافات المتكررة وتنظيف الأطراف
        fileName = fileName.replace(/\s+/g, ' ').trim();
        
        return fileName;
    }
});

// تسجيل المعالج
XF.Element.register('xbt-uploader', 'XBTUploader');

