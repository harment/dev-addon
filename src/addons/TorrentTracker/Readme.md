# Welcome to TorrentTracker XenForo 2 Addon Docs

XenForo 2 Torrent Tracker Addon is used for making a XenForo Forum compatible with Torrent System using XBT as the backend for Tracker.

## Features
* `Torrent System` - Implements Torrent System throughout Forum.
* `Freeleech` - Make Freeleech and Request Freeleech Options.
* `Reseed Request` - Reseed Request Option.
* `Bonus System` - Bonus is given to users for seeding.
* `Ban IP` - An IP Can be banned from Admin Panel.
* `Ban Client` - A Client Can be banned from Admin Panel.
* `User Criteria` - User Criteria for Usergroup Promotion and Notice.
* `Multiple Announce Url` - Adds the ability to use multiple announce urls
* `Peer List & Snatch List Page` - Dedicated page for Peers and Snatchers Lists to show all peers and snatchers.


## Changelogs

### 1. Addon Version 2.0.0:
```
1. Changed Guzzle configuration for Guzzle 6 of xf 2.1.0
2. Fixed My Bonus Page showing footer incorrectly
3. Fixed Top 10 Page, Changed Likes to Reactions to support xf 2.1.0 reactions.
4. Added Cron Jobs for Torrent Tracker
5. Fixed Exceptions Implementation.
6. Fixed some phrases implementations.
8. Added Table and Column Check Conditions while installing addon
9. Fixed Ratio display on sidebar widget
```
### 2. Addon Version 2.0.1:
```
1. Addon Updated to 2.0.1
2. Options->maxFreeleechrequest size changed to minFreeleechrequest size
3. Now freeleechrequest size needs to be entered in MB instead of KBytes
4. Freeleech Request size check at requesting freeleech implemented.
6. Added My Bittorrent Stats widget.
```
### 3. Addon Version 2.0.3:
```
1. Fix Seed List , Pagination Issue in Members tab
2. Send Alert on Request ReSeed Fixed
3. Fixed Phrases
```
### 4. Addon Version 2.0.4:
```
1. Fixed ['topUploaders'] not found error error 
2. Added http:// to tracker display while creating thread
```
### 5. Addon Version 2.0.5:
```
1. Added Freeleech Prefix to Thread View for Freeleech Torrents
2. Changed download condition from Must Like to Download => Must React to Download to Support Xenforo 2.1.0 Reactions.
3. Fixed Template Modification for user edit, showing freeleech desc instead of freeleech text
```
### 6. Addon Version 2.1.0:
```
1. Fixed Exception Catching at some Places
2. Added torrent stats to member tooltip
3. Added torrent stats to member profile
4. Fixed Freeleech System
```
### 7. Addon Version 2.1.1:
```
1. Added Links to My Bittorent Stats Top Download and Upload Icons
2. Fixed Delete Button Display in Client Ban Manager and IP Ban Manager
3. Fixed Exception Implementations at Various Locations
4. Fixed Phrases
5. Rearranged TorrentTracker Settings Page
6. Icon Fixes , Tooltips Added in Torrent Pages, added Colors
any many more minor improvements and fixes.
```
### 8. Addon Version 2.1.2:
```
1. Implemented Cron Jobs properly so that it clears old torrent logs data from db
2. Some minor bug fixes and updates.
```
### 9. Addon Version 2.1.3:
```
1. Implemented Promotion System for TorrentTracker
2. Some minor bug fixes and updates.
```
### 10. Addon Version 2.1.4:
```
1. Fix-2 for Reseed Alert - Now Alert will be send to torrent creater aswell. 
2. Fixed Permission Issues caused by Addon
```
### 11. Addon Version 2.1.5:
```
1. Implemented Default Permissions set after install
2. Added Default torrent values for new user registration
3. Some minor bug fixes and updates.
```
### 12. Addon Version 2.1.6:
```
1. Fixed Wrong Torrent Alert going in Request Reseed
2. Fixed Alert not going to Torrent Uploader.
```
### 13. Addon Version 2.1.7:
```
1. Some Minor Fixes and Changes
```
### 14. Addon Version 2.1.8:
```
1. Fixed User Group Promotion Data not storing.
```
### 15. Addon Version 2.2.0:
```
1. Added User Criteria of Ratio Less Than X and Ratio Greater Than X , which can be used to promote/demote users based on ratio or show notice to users based on ratio.
2. Changed default seed interval to 3600 seconds instead of 1800 seconds.
3. Included IP Address in Peer List
4. Now If User don't have access to forum than torrents from those forums aren't displayed. viewContent Permission is implemented so if user have permission to view content of that forum than only its torrents will be listed in torrents page.
5. Deleted Lots of Unused Files as those were based on XenForo 1.5.x and not used in XenForo 2
6. Deleted xftt_active_torrents everything from Xenforo 2 Addon as it was used in Xenforo 1 Addon of Tracker.
7. Now Peers of Torrents are not deleted even if the peers are older as it keep records of upload, download and seeding time of users.
```

### 16. Addon Version  2.2.1

```
1. Added Pagination for Peer List and Snatchers List if List is greater than 20
2. Peer List is set to Order By Active Peer Lists, so Firstly Active Peer Lists will be shown after than inactive peer lists.
3. Some Minor Bug Fixes and Improvements.
```

### 17. Addon Version 2.2.3
```
1. Now Seedbonus values is not multiplied by 10 or divided by 10, For 1 Seedbonus only 1 will be inserted in db instead of 10.
Note : Modification in xbt tracker's server.cpp is required so that only 1 seedbonus is added for 1 seedbonux.

Note : While Upgrading the addon with this new version it will automatically divide seedbonus of all users by 10 to maintain the proper seedbonus for supporting the new change.

Make the following changes to xbt\Tracker\server.cpp :


C++:
// Bonus Point System //
        if (m_config.m_seedbonus && user->timespent_seeding >= m_config.m_seedbonus_interval) {
            seedbonus = 10 * (user->timespent_seeding / m_config.m_seedbonus_interval);
            user->timespent_seeding = 0;
to

C++:
// Bonus Point System //
        if (m_config.m_seedbonus && user->timespent_seeding >= m_config.m_seedbonus_interval) {
            seedbonus = 1 * (user->timespent_seeding / m_config.m_seedbonus_interval);
            user->timespent_seeding = 0;
```

### 18. Addon Version 2.2.4
```
1. Comment System Fixed - Now Comment to Torrent file is added while uploading/downloading file. (Add Comment in Options of Addon)
2. Cleanup and Small Fixes

Note : Changes as mentioned in Update 2.2.3 for xbt/server.cpp file must be done before using this version.
```

### 19. Addon Version 2.2.5 Beta 1

```
1. Now Peer List is Ordered By active peers.
```

### 20. Addon Version 2.2.5 Beta 2

```
1. Now Up/Down Speed are shown in proper format under peer list.
```

### 21. Addon Version 2.2.5 Beta 3

```
1. Update Made in User Upgrade System, Now you can specify Upload Credit for each User Upgrade , so when a user upgrade takes place upload credit will be added to user profile and it will stay there even if he is downgraded. It's going to be useful if you want to give your ViP Upload credits which they can enjoy after VIP Expires.
```

### 22. Addon Version 2.2.5 Beta 4

```
1. Fix for Error: Call to a member function canView() on null src/addons/TorrentTracker/Pub/Controller/Torrent.php:546
```

### 23. Addon Version 2.2.5 Beta 5

```
1. Fixed an Issue with style, if there is sidebar on Thread View Peer List was not displaying properly.
```

### 24. Addon Version 2.2.5 Beta 6

```
1. Fixed :- Seeding List of Users was not showing Completely
```

### 25. Addon Version 2.2.5 Beta 7

```
1. Now Torrents are Ordered By Torrent Creation Time on Torrent Page, Earlier They were Ordered By Last Announce Time.
```

### 26. Addon Version 2.2.5 Beta 8

```
1. Re-Fixed the Seed List not showing properly, Implemented new way for it to work.
```

### 27. Addon Version 2.2.5 Beta 9

```
1. Fixed Issue on User Upgrade Degrade : Secondary Usergroup was not being removed.
2. Fixed : Error: Call to undefined method XF\Mvc\Reply\Redirect::getparam()
```

### 28. Addon Version 2.2.6 Beta 1

```
1. Fixed Filter Issues on Torrent Page (Order by Size, Seeding etc..)
```



# Exclusive Edition Begins from Here

### 29. Addon Version 2.2.7

```
1. Now You can have one more Tracker. It supports 2 Trackers now, however using 2nd tracker is completely optional.
2. Now it supports https, so you can use https in your Tracker
3. Minor bug fixes and updates
```

### 30. Addon Version 2.2.7.1

```
1. Minor Bug Fixes
2. Show http and https properly based on options in tracker settings.
```

### 31. Addon Version 2.2.9

```
1. Multiplier :- Forum Based, User Based, Upgrade Based and Global Upload and Download Multiplier Implemented.
2. Last Announce Time Displayed in Peer List.
3. Option to Control Torrent Category Icon Added.
4. Removed Unnecessary Code.
5. Proper Implementation of Phrases.
6. Option to Add Attachment Tag/Postfix to Torrent File Name on Downloading.
```

### 32. Addon Version 2.2.9.1

```
1. Fixed a bug which was present after implementing the Attachment Tag Option.
```

### 33. Addon Version 2.2.9.2

```
1. Fixed Some Permission Related Bugs
2. Fixed Freeleech Requests Page Showing to all regardless of Permissions
3. Added Requested By Field in Freeleech Requests
4. Now on Freeleech Requests , alert goes to all Staff Members and on Accept or Rejection of Request by any staff, alert gets deleted from all other staff members alert box.
5. Now Upload and Download Multiplier Supports 0 Value.
6. Some little fixes here and there.
```

### 34. Addon Version 2.2.9.3

```
1. Fix for Multiplier Not Working Properly
```

### 35. Addon Version 2.2.9.4

```
1. Another Fix for Multiplier Issue
2. Fixes Issue with Freeleech Alert , was showing wrong thread in alert.
```

### 36. Addon version 2.2.9.5

```
1. Fixes the issue with Icon Size Option.
2. Shows Seedbonus in Tooltip and User Profile.
```

### 37. Addon version 2.2.9.6

```
1. Introducing Sticky Torrents Option (Now Stick Torrents at Top in Homepage).
2. Show Freeleech Torrents Option in Torrent Page. (Now find all Freeleech Torrents, or Freeleech Torrents from a particular user, or Freeleech Torrent with a Particular File Name).
```

### 38. Addon Version 2.2.9.7

```
This version brings minor changes to Sticky Torrents.

1. Now Limit Maximum No. of Torrents that can be sticked.
2. Now change background color of Sticked Torrents.
3. Fixed Torrent Icon Issue with Friendly Url's
```

### 39. Addon Version 2.2.10

```
1. Fixes the issue of filters in torrent page, earlier the filters was working only on 1st page and not continuing to next pages.
2. Introduced Auto Move Inactive Torrents after x Days option, It lets you move the Inactive Torrents after some days of in-activeness of torrents.
```

### 40. Addon Version 2.2.10.1

```
1. Fixed the permission of Node Category on Sidebar and Search Option - Earlier it was based on View Node Content Permission, Now it is based on View Node Permission. So if you select a node as private , then that won't be shown to unauthorized users.
```

### 41. Addon Version 2.2.10.2

```
1. Fixes the Utorrent Icon on Member Profile.
```

### 42. Addon Version 2.2.10.3

```
1. Implemented a check for auto torrent move feature, now move only takes place if the torrent have a related thread. In some cases for old forums there might be Torrents whose thread have been deleted. In Those cases the mover was throwing an error. - Fixed Now
```

### 43. Addon Version 2.2.10.4

```
1. Implemented Colors using Classes and Style Properties to better adjust and easily modify with different themes.
```

### 44. Addon Version 2.2.10.5

```
1. Fixed a Bug Causing Torrents not to display in some cases.
2. Now if a Torrent is present in Non-Torrent Forum Category than that Torrent won't show in Torrent Page.

Note :- This Fix has little bit changed the way Torrent System was working, so it is highly recommended to backup your site , before upgrading to this release.
```

### 45. Addon Version 2.2.10.6

```
1. Fixes an Issue, causing errors to generate on moving Non-Torrent Threads after last Update.
```

### 46. Addon Version 2.2.10.7

```
1. Advanced Options Added to manage Announce Interval, Seedbonus Interval and Seedbonus Amount

Note :- Everytime you update these settings, you will have to restart the xbt to have effect of these settings on xbt end.

Important :- You need to update your XBT files and recompile them with the latest release to make seedbonus amount work properly with database.
```

### 47. Addon Version 2.2.10.8

```
1. Now you can easily manage Seed Bonus Exchange Points From Admin Panel
```

### 48. Addon Version 2.2.10.9

```
1. Added Feature to Disable Ratio Check on Freeleech Torrents (Implies to both Global Freeleech and Individual Freeleech)
```

### 49. Addon Version 2.2.11

```
1. Fixes an issue caused by the last update of not adding passkey to the downloaded torrent.
```

### 50. Addon Version 2.2.12

```
1. Fixes a Permission issue, where if user is not allowed to see content of other users in a forum, torrent of such forum is displyed in torrent list and is downloadable.
2. Fixes an issue where if a users Leeching permissions are disabled, he is still able to download the torrent.
3. Implemented an Feature through which you can allow users to download the torrents they have previously downloaded, even if their ratio is below minimum required ratio.
4. Removed Un-used Files and Functions which were part of XenForo 1
5. Files and Folders are Re-organized to satisfy the XenForo standards and guidelines. 

Note:- Somes Files have been deleted, some Files are re-organzied, so you will need to delete the addon files and re-upload the files.

Note:- Make sure to make backup of website to avoid any issues before upgrading to this version.
```

### 51. Addon Version 2.2.12.1
```
1. Fixed Timespent Viewing for Seedbonus, Now shows timespent in mins, hrs and days.
2. Removed Duplicate code from Torrent Index Page
3. Approach for Displaying User Torrent stats throughout website has been changed to a better option.
4. Seedbonus is now displayed in postbit aswell
```

### 52. Addon Version 2.2.12.2
```
1. Changed Structure of File Info from Serialized Array to Json Array
2. Fixed :- Errors being generated on threads which have deleted users comments/posts in it
```

### 53. Addon Version 2.2.12.5
```
1. Improves the query on Torrent page
2. Now Torrents which are removed from public view won't be displayed in Torrents Page

```

### 54. Addon Version 2.2.12.6
```
1. Fixes Error on Member Torrents Caused due to Typo
2. Corrected Spell mistakes at various places
```

### 55. Addon Version 2.2.12.7
```
1. Fixed Issue where on Clicking IP Manager in AdminCP Redirected to Client Manager
2. Now Added a usergroup permission where users can download their own torrents.
3. Now added a criteria for User has uploaded at least X torrents
4. Now on Forums with Torrent Category displays Upload Torrent and Attach Torrent File on buttons.
```

### 56. Addon Version 2.2.12.8
```
1. Released a Patch for issue related to unknown user_id on torrent download
```

### 57. Addon Version 2.2.13
