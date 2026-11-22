# Setting Up Your Site

{% hint style="warning" %}
Settings > Reading > "Your homepage displays" must be set to "A static page," with a page selected. Otherwise, Disable Blog's redirects will not work, and your blog index stays reachable on the front end.
{% endhint %}

### Set a static homepage

1. From the WordPress admin screen, navigate to Settings > Reading.
2. Under "Your homepage displays," choose "A static page."
3. From the Homepage dropdown, select the page you want visitors to land on.
4. Click "Save Changes."

### The optional Posts page setting

The "Posts page" dropdown on the same screen is not required. You can leave it unset.

If you do set one, Disable Blog redirects it to your homepage, the same as it redirects any other blog URL. If you set the Posts page to the same page as your homepage, the plugin shows an admin notice telling you the two need to be different pages.

### Why a static homepage is required

Disable Blog works by redirecting posts, archives, and the blog index to your homepage. To send visitors anywhere, it needs to know which page that is.

If "Your homepage displays" is still set to "Your latest posts," WordPress has no static front page, and the plugin has nothing to redirect to. None of its front-end redirects run in that case, so posts, archives, and the blog index all stay live and reachable.

The rest of the plugin still works. Posts stay out of search results, the REST API, and the admin menu either way. It is only the redirects that need a homepage.

### The admin notice

While a static homepage is missing, Disable Blog shows a red admin notice: "Disable Blog is not fully active until a static page is selected for the site's homepage." On the Plugins screen and on list screens like Pages, the notice links to Reading Settings. On the Reading Settings screen itself, it tells you to select a page for your homepage below instead. You'll see this notice on those three screens until a homepage is set.
