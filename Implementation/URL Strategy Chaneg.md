Url strategy change.

When user starts plugin, on the wizard he has the choice between choosing the main plugin page to be the storys archive or a custom page.

In plus of this, user can change also if he wants the plug-in to work with or without a base slug.

The goal to work with a base slug is to isolate all the plug-in logic from the WordPress pages and avoid url conflicts.

When user choses a base slug, a page with the id of the base slug is created, and, all the system pages are created under it, in hierchary. If the user doesn't chose a base slug, the pages are created without the hierchary.

Knowing that a archive page url is created and also a home page is created and always exists in every scenario. 

this creates a 4 possibility scenario.

• Base slug + Main page is the archive page: main page of the plug-in exhibits the same content of the user archive page. (it's the same page on different urls) and user home page never displays 
• Base slug + Main page is a custom page: home page created on the wizard is the home page
• No Base slug + Main page is the archive page: main page of the plug-in exhibits the same content of the user archive page. and user home page never displays. This changes the core WordPress settings to make it's home page the plug-in archive page ((it's the same page on different urls) the goal is to have archive user page display on the root url 
• No Base slug + Main page is a custom page: This changes the core WordPress settings to make it's home page the plug-in home page.

If user changes the WordPress core home page settings while having the no base slug option, the plug-in homepage will break. So a warning must be shown on wp admin panel with a fix button that will fix this.


In terms of uix. On the wizard step thst prompts the url names, at the top, we add a card that says Main Url : WordPress main URL or WordPress with base_slug. Explaining that using base slug isolates plugin from main WordPress pages and logic. Using main  url root will overwrite WordPress main homepage settings as well as archives, and might conflit with other plug-ins. Change to base slug if incompatibilités arise.

If no base slug if chosen, the field on the screen to chose base slug disappears, and the live url previews will also delete the base slug from the url construction.

To avoid conflits, before creating the page, we check for conflits in ids and names, and if a page already exists, we add - ff to the of. Example url/members becomes url/members-ff

User can latter switch modes on the plug-in settings. If ,he chose not to use the base slug at wizard and then he chooses to use it later on settings, all the system pages will be rebuilt under the hierarchy of the base slug page, and vice versa. This will result in broken followed links and search engines will need to remap the site all over again. The user needs to be warned of this, but it's a conscious choice. The user needs to be given the option of choosing between modes seamlessly, but be aware of the consequences.