<?php

class GolemBridge extends FeedExpander {
	const MAINTAINER = 'miicha';
	const NAME = 'Golem Online Bridge';
	const URI = 'https://www.golem.de/';
	const CACHE_TIMEOUT = 1800; // 30min
	const DESCRIPTION = 'Returns the full articles instead of only the intro';
	const PARAMETERS = array(array(
		'category' => array(
			'name' => 'Category',
			'type' => 'list',
			'values' => array(
				'Alle News'
				=> 'https://rss.golem.de/rss.php?feed=ATOM1.0',
				'Foto'
				=> 'https://rss.golem.de/rss.php?ms=foto&feed=ATOM1.0',
				'Software Entwicklung'
				=> 'https://rss.golem.de/rss.php?ms=softwareentwicklung&feed=ATOM1.0',
				'Security'
				=> 'https://rss.golem.de/rss.php?ms=security&feed=ATOM1.0'
			)
		),
		'limit' => array(
			'name' => 'Limit',
			'type' => 'number',
			'required' => false,
			'title' => 'Specify number of full articles to return',
			'defaultValue' => 5
		),
        'cookie' => array(
            'name' => 'Cookie',
            'required' => false,
            'title' => 'Set cookie',
            'defaultValue' => "golem_consent20=cmp|220101"
        )
	));
	const LIMIT = 5;

	public function collectData() {
        //$headers[] = 'cookie: sessionid=' . $sessionId;
        error_log("Message here",0);
		$this->collectExpandableDatas(
			$this->getInput('category'),
			$this->getInput('limit') ?: static::LIMIT
		);
	}

	protected function parseItem($feedItem) {
		$item = parent::parseItem($feedItem);
        $item['content'] = "";
		//$item['uri'] = explode('?', $item['uri'])[0] . '?seite=all';

        $headers = array();
        $headers[] = 'cookie: '. $this->getInput('cookie') ?: 'golem_consent20=cmp|220101';
        //$item['uri'] = "https://www.golem.de/news/radeon-rx-680m-im-test-die-mit-abstand-schnellste-integrierte-grafik-2203-163442.html";
		$article = getSimpleHTMLDOMCached($item['uri'], 86400, $headers);

		if ($article) {
            if(str_contains($article, 'GolemConsent.setCustomConfig(')){
                $item['content'] = "consent cookie seems to be wrong...";
                return $item;
            }
			$article = defaultLinkTo($article, $item['uri']);
			$item = $this->addArticleToItem($item, $article);
            $nextLink = $article->find('a[id*="jtoc_next"]', 0);
            while ($nextLink){
                $uri = $nextLink->href;
                if(!str_contains($uri, '://')){
                    $uri = self::URI . $uri;
                }
                Debug::log("next link:");
                Debug::log($uri);
                $article = getSimpleHTMLDOMCached( $uri, 86400, $headers);
                $nextLink = $article->find('a[id*="jtoc_next"]', 0);
                $item = $this->addArticleToItem($item, $article);
            }
		}

		return $item;
	}

	private function addArticleToItem($item, $article) {

		//if($author = $article->find('[itemprop="author"]', 0))
		//	$item['author'] = $author->plaintext;

        Debug::log($article);

		$content = $article->find('div[class*="formatted"]', 0);
		//if ($content == null)
		//	$content = $article->find('#article_content', 0);

		foreach($content->find('p, h3, ul, table, pre, img') as $element) {
            if(!(str_contains($element, '<ul>') && str_contains($element, 'mit ausgeschaltetem Javascript'))){
                $item['content'] .= $element;
            }
		}

		foreach($content->find('img') as $img) {
			$item['enclosures'][] = $img->src;
		}

		return $item;
	}
}
