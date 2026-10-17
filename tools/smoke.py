"""Read-only smoke checks. Run against a local PHP server: python3 tools/smoke.py."""
from html.parser import HTMLParser
from pathlib import Path
import hashlib
import json
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
SITE = json.loads((Path(__file__).resolve().parent.parent / 'data/site.json').read_text())


def request(path, expected=200):
    try:
        response = urllib.request.urlopen(BASE + path)
    except urllib.error.HTTPError as error:
        response = error
    assert response.code == expected, (path, response.code, expected)
    text = response.read().decode('utf-8')
    assert 'Fatal error:' not in text and 'Warning:' not in text, path
    return text


class VisibleText(HTMLParser):
    def __init__(self):
        super().__init__()
        self.skip = 0
        self.text = []

    def handle_starttag(self, tag, attrs):
        if tag in ('script', 'style'):
            self.skip += 1

    def handle_endtag(self, tag):
        if tag in ('script', 'style'):
            self.skip -= 1

    def handle_data(self, data):
        if not self.skip:
            self.text.append(data)


home = request('/')
assert '/works.php' in home and 'همه کارها' in home
assert 'class="site-logo"' in home
visible = VisibleText()
visible.feed(home)
for decoration in ('NEGAH', 'CREATIVE STUDIO', 'AVID', 'POINT OF VIEW', 'LESS, BUT BETTER'):
    assert decoration not in ' '.join(visible.text), decoration
request('/works.php')
request('/works.php?category=film')
request('/admin.php')
for project in SITE['projects']:
    key = project.get('uid') or hashlib.sha256((project['title'] + '|' + project['description']).encode()).hexdigest()[:16]
    page = request('/project.php?id=' + urllib.parse.quote(key))
    assert project['title'] in page and 'درباره این پروژه' in page
request('/project.php?id=missing', 404)
for private in ('/data/auth.json', '/data/site.json', '/.git/HEAD', '/core.php', '/views.php', '/admin-projects.php', '/tools/preview.mjs'):
    request(private, 404)
print('PASS public pages, stable project links, logo, Persian artwork and private-route protection')
