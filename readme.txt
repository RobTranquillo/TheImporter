
TheImporter
Generate new worpress-posts from an directory, including pictures.

If you have to import somtimes or often new posts to your blog, probably if you have to syncronize your blog with an external data, here is the ability to import the complete bunch of posts from your data* everytime when your data is being updated or by hand.
(*) it is not the same as if you import from another wordpress blog, this here is generic!

With TheImporter you have the ability to generate new posts everytime when the plugin is running, probably by hand or by an cronjob. TheImporter reads the given directory, imports all XML-files with the right stucture and imports also pictures as post relative. Possible usable contents within the XML are title,post,customfields,tags,author,category. Additionally pictures can be put into the directory side by the XML and would be imported and linked to the new post described in the XML file.

If you came frome another database to import into your blog, just take care that all data relating to one post are saved in one xml-file. If an XML file layes down in an directory and have some pictures around, these will be imported to and thumbnails will be generated automaticly.

Category-handling
On the plugin-page, you can choose an parent category, all posts will be posted under this top-category. And you have the option to insert an additionally category into the xml file. This category will be also inserted as child category of the choosen parent-category.

 UserGuide
	this plugin provides functionality to import an lot of posts reading direct from directory.
	0) all plugins-settingss are handled by a ini-file
	1) every texual post content is placed in an single xml-file(must be in UTF8!) in an different directory
	2) every single directory can contain other files they can be data of the post (just pictures now)
	3) the text file contains sections for the post data: title,post,customfields,tags, author, category (slag,exzerpt are not supported now)
	4) The author setted in the plugin settings will be stronger than an author in the textfile(!). If nowhere an author set, the plugin sets the id 1

 Hints
	- if you want to use html tags inside your post or title, make save that befor saving them as xml they are converted with htmlentities()!