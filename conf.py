import sys, os
from sphinx.highlighting import lexers
from pygments.lexers.web import PhpLexer


lexers['php'] = PhpLexer(startinline=True, linenos=1)
lexers['php-annotations'] = PhpLexer(startinline=True, linenos=1)
primary_domain = 'php'

extensions = []
templates_path = ['_templates']
source_suffix = '.rst'
master_doc = 'index'
project = u'Doctrine ORM GraphQL'
copyright = u'2024 API Skeletons'
