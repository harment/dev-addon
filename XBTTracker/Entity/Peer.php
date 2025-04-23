<?php
// src/addons/XBTTracker/Entity/Peer.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * Peer Entity
 * Stores information about torrent peers (seeders/leechers)
 * 
 * كيان النظير
 * يخزن معلومات عن نظراء التورنت (المرسلين/المستقبلين)
 *
 * @property int $peer_id
 * @property int $torrent_id
 * @property int $user_id
 * @property string $peer_id_binary
 * @property string $ip
 * @property int $port
 * @property int $uploaded
 * @property int $downloaded
 * @property int $left_bytes
 * @property bool $seeder
 * @property int $first_announce
 * @property int $last_announce
 * @property bool $completed
 * @property bool $hit_and_run_warned
 * @property string $passkey
 * 
 * @property-read \XF\Entity\User $User
 * @property-read \Harment\XBTTracker\Entity\Torrent $Torrent
 */
class Peer extends Entity
{
    /**
     * Define the entity structure
     * تعريف هيكل الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_peers';
        $structure->shortName = 'XBTTracker:Peer';
        $structure->primaryKey = 'peer_id';
        
        $structure->columns = [
            'peer_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'torrent_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'peer_id_binary' => ['type' => self::BINARY, 'required' => true, 'maxLength' => 20],
            'ip' => ['type' => self::STR, 'required' => true, 'maxLength' => 45],
            'port' => ['type' => self::UINT, 'required' => true, 'min' => 1, 'max' => 65535],
            'uploaded' => ['type' => self::UINT, 'default' => 0],
            'downloaded' => ['type' => self::UINT, 'default' => 0],
            'left_bytes' => ['type' => self::UINT, 'required' => true],
            'seeder' => ['type' => self::BOOL, 'required' => true],
            'first_announce' => ['type' => self::UINT, 'required' => true],
            'last_announce' => ['type' => self::UINT, 'required' => true],
            'completed' => ['type' => self::BOOL, 'default' => false],
            'hit_and_run_warned' => ['type' => self::BOOL, 'default' => false],
            'passkey' => ['type' => self::STR, 'required' => true, 'maxLength' => 40]
        ];
        
        $structure->getters = [
            'connection_time' => true,
            'is_active' => true,
            'display_speed' => true
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Torrent' => [
                'entity' => 'XBTTracker:Torrent',
                'type' => self::TO_ONE,
                'conditions' => 'torrent_id',
                'primary' => true
            ]
        ];
        
        return $structure;
    }
    
    /**
     * Get the connection time (duration between first and last announce)
     * الحصول على مدة الاتصال (المدة بين أول وآخر إعلان)
     * 
     * @return int Time in seconds
     */
    public function getConnectionTime()
    {
        return max(0, $this->last_announce - $this->first_announce);
    }
    
    /**
     * Check if the peer is considered active (announced recently)
     * التحقق مما إذا كان النظير نشطًا (أعلن مؤخرًا)
     * 
     * @param int $maxIdleTime Maximum idle time in seconds (default 30 minutes)
     * @return bool
     */
    public function getIsActive($maxIdleTime = 1800)
    {
        return ($this->last_announce >= \XF::$time - $maxIdleTime);
    }
    
    /**
     * Get the peer connection speed for display
     * الحصول على سرعة اتصال النظير للعرض
     * 
     * @return array Contains upload and download speeds in human-readable format
     */
    public function getDisplaySpeed()
    {
        $connectionTime = $this->getConnectionTime();
        
        if ($connectionTime < 60) { // Require at least 1 minute of connection
            return [
                'upload' => '? KB/s',
                'download' => '? KB/s'
            ];
        }
        
        $uploadSpeed = $this->uploaded / $connectionTime;
        $downloadSpeed = $this->downloaded / $connectionTime;
        
        return [
            'upload' => $this->formatSpeed($uploadSpeed),
            'download' => $this->formatSpeed($downloadSpeed)
        ];
    }
    
    /**
     * Format speed in bytes/second to human-readable format
     * تنسيق السرعة من بايت/ثانية إلى تنسيق مقروء للإنسان
     * 
     * @param float $bytesPerSecond
     * @return string
     */
    protected function formatSpeed($bytesPerSecond)
    {
        $units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
        $unitIndex = 0;
        
        while ($bytesPerSecond >= 1024 && $unitIndex < count($units) - 1) {
            $bytesPerSecond /= 1024;
            $unitIndex++;
        }
        
        return round($bytesPerSecond, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Check if the peer has hit and run potential
     * التحقق مما إذا كان النظير لديه احتمالية ضرب واهرب
     * 
     * @return bool
     */
    public function hasHitAndRunPotential()
    {
        return ($this->completed && !$this->seeder);
    }
    
    /**
     * Get the ratio for this peer on this torrent
     * الحصول على نسبة الرفع/التنزيل لهذا النظير على هذا التورنت
     * 
     * @return float
     */
    public function getRatio()
    {
        if ($this->downloaded == 0) {
            return $this->uploaded > 0 ? INF : 0;
        }
        
        return $this->uploaded / $this->downloaded;
    }
    
    /**
     * Get percent completed (0-100)
     * الحصول على نسبة الاكتمال (0-100)
     * 
     * @return float
     */
    public function getPercentCompleted()
    {
        if (!$this->Torrent || !$this->Torrent->size) {
            return $this->seeder ? 100 : 0;
        }
        
        $completed = $this->Torrent->size - $this->left_bytes;
        $percent = ($completed / $this->Torrent->size) * 100;
        
        return min(100, max(0, $percent));
    }
}