import guzzle_sphinx_theme

project = 'doctrine-orm-graphql'
author = 'Tom H Anderson <tom.h.anderson@gmail.com>'
copyright = '2023 API Skeletons <contact@apiskeletons.com>'
master_doc = 'index'
show_sphinx = True
commit = True
last_updated = True
pygments_style = 'sphinx'
html_favicon = 'favicon.ico'

html_theme_path = guzzle_sphinx_theme.html_theme_path()
html_theme = 'guzzle_sphinx_theme'

extensions.append("guzzle_sphinx_theme")

html_theme_options = {
    "project_nav_name": "Doctrine ORM GraphQL",
}
