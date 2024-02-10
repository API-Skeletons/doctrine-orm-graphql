import sys, os
from sphinx.highlighting import lexers
from pygments.lexers.web import PhpLexer
import guzzle_sphinx_theme

lexers['php'] = PhpLexer(startinline=True, linenos=1)
lexers['php-annotations'] = PhpLexer(startinline=True, linenos=1)
primary_domain = 'php'

templates_path = ['_templates']
source_suffix = '.rst'
master_doc = 'index'
project = u'doctrine-orm-graphql'
author = 'Tom H Anderson <tom.h.anderson@gmail.com>'
copyright = '2024 API Skeletons <contact@apiskeletons.com>'
version = '1'

html_title = "GraphQL Driver for Doctrine ORM Documentation"
html_short_title = "Doctrine ORM GraphQL"
html_favicon = 'favicon.ico'
html_theme_path = guzzle_sphinx_theme.html_theme_path()
html_theme = 'guzzle_sphinx_theme'

# Register the theme as an extension to generate a sitemap.xml
extensions = []
extensions.append("guzzle_sphinx_theme")

html_sidebars = {
    '**': ['logo-text.html', 'globaltoc.html', 'searchbox.html']
}

html_theme_options = {
    "project_nav_name": "Doctrine ORM GraphQL",
    "base_url": "https://doctrine-orm-graphql.apiskeletons.dev",
}
