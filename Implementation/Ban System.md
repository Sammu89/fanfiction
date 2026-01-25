Ban system


A ban system already exists, that blocks users or specific stories. We need to make sure of how this works.

A story status blocked means that, story and it's chapters are hidden from the users, except the author and admin and moderators. Author can see the story listed and even click to see the page, but edit and delete buttons are disabled, meaning they can see their stories and chapters but they cannot edit or delete. Admin and moderators can have all the permissions to edit view, delete etc. 

Banning a user will set his status as banned, and all of it's stories also banned. Meaning there is a metatag on the post type of stories that defines the reason of the ban.

On the message field of the story and chapter, we will display the block reasons. 

 The stories are blocked because user is banned.


Stories can also be blocked individually. On that case, an admin / mod will need to tell why via a drop down list. The reason is saved on the story post meta and the message will display, your story was blocked on (timestamp) because (reason).
Stories can be blocked individually via the stories listing in admin page, and also via a block button that is available only to admins or moderators.


A edge case, is a block because wp admin changed rules. Imagine that the website allowed stories with sexual content, and they were created with these warnings. Later, when admin decides to change the rules and not allow it, the stories that have those warnings will be set to draft. So they are not blocked, but they will be set to draft and thus being invisible to users. All the normal draft buttons are allowed, so author can change the content of story to comply with the rules. A message motive should also appear saying, story was automatically drafted because no more sexual / pornographic contents are allowed and user can chose to edit story or delete.


Moderating actions that leads to block of users will be logged somehow (a table? A transient?) and displayed on the moderator log under the moderation panel of the fanfic plugin.

Example

Timestamp: (admin name) blocked user (username) because (reason)

Timestamp: (admin name) blocked story (story name) because (reason)