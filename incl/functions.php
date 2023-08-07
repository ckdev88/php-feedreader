<?php
global $DATA;
function pp($content, $bg = 'lightgrey', $fg = 'black')
{
	ob_start();
	echo '<pre style="background-color:' . $bg . ';color:' . $fg . '"><br/>';
	print_r($content);
	echo '</pre>';
	$r = ob_get_clean();
	echo $r;
}

function getFeedsArr()
{
	$retFeedsArr = array();
	$feedsData = file_get_contents('incl/feeds.json');
	$feedsObjects = json_decode($feedsData);

	foreach ($feedsObjects as $key => $val) {
		if ($val->url != '') {
			$retFeedsArr[] .= $val->url;
		}
	}
	return $retFeedsArr;
}

function expandButton($count)
{
	return '<button href="#" onclick="javascript:expand(' . $count . ')" id="msg-description-button' . $count . '">+</button>';
}

function msgDescription($count, $date, $host, $title, $description, $link)
{
	$html = '';
	$html .= '<div class="msg-description" id="msg-description' . $count . '">';
	$html .= '<div class="pubdate">pubdate:' . $date . '</div>';
	$html .= '<span class="host">' . $host . '</span>';
	$html .= '<h2>' . $title . '</h2>';
	$html .= html_entity_decode($description, ENT_QUOTES, 'UTF-8');
	$html .= '<br/><br/>';
	$html .= '<a href="' . $link . '" target="nieuwsartikel">&rarr; Verder op ' . $host . '</a>';
	$html .= '</div>';
	return $html;
}

function msgLink($link, $date, $title, $host = '')
{
	$html = '';
	$html .= '
		<a 
			href="' . $link . '" 
			target="_blank">
		';
	$html .= '<div class="pubdate">' . $date . '</div>';
	$html .= '<div>' . $title;
	if ($host != '') {
		$html .= '<span class="host"> - ' . $host . '</span>';
	}
	$html .= '</div>';
	$html .= '</a>';
	return $html;
}

function getFeeds($groupby = 'datum', $timeframe)
{
	$feeds = getFeedsArr();
	$html = '';

	$nu = strtotime('now');
	$timeframe = $nu - $timeframe;
	$count = 0;
	if ($groupby == 'datum') {
		$entries = array();
		foreach ($feeds as $feed) {
			$xml = simplexml_load_file($feed, "SimpleXMLElement", LIBXML_NOERROR |  LIBXML_ERR_NONE);
			$entries = array_merge($entries, $xml->xpath('//item'));
		}

		if (!empty($entries)) {
			usort($entries, function ($feed1, $feed2) {
				return strtotime($feed2->pubDate) - strtotime($feed1->pubDate);
			});
		} else {
			$html .= 'no entries';
		}
		$html .= '<ul>';

		foreach ($entries as $entry) {
			if (strtotime($entry->pubDate) > $timeframe) {
				$pubDate = strftime('%m/%d/%Y %I:%M %p', strtotime($entry->pubDate));
				$pubDate2 = strftime('%H:%M', strtotime($entry->pubDate));
				$count++;

				$html .= '<li class="msg">';
				$html .= expandButton($count);
				$html .= msgLink($entry->link, $pubDate2, $entry->title, str_replace('www.', '', parse_url($entry->link)['host']));
				$html .= msgDescription($count = $count, $date = $pubDate, $host = str_replace('www.', '', parse_url($entry->link)['host']), $title = $entry->title, $description = $entry->description, $link = $entry->link);
				$html .= '</li>';
			}
		}
		$html .= '</ul>';
	} else {  // echo 'hier de ELSE: sort per blog';
		$channels = array();
		foreach ($feeds as $feed) {
			if ($count < 100) {
				$xml = simplexml_load_file($feed, "SimpleXMLElement", LIBXML_NOERROR |  LIBXML_ERR_NONE);
				$channels = array_merge($channels, $xml->xpath('//channel'));
			}
			$count += 1;
		}
		usort($channels, function ($feed1, $feed2) {
			return strtotime($feed2->pubDate) - strtotime($feed1->pubDate);
		});

		$telChannels = 0;
		$idTeller = 0;
		foreach ($channels as $channelKey => $channelVal) {
			$telChannels++;
			$telChannelDetails = 0;
			$blogTitle = $channelVal->title;

			$opencount = 0;
			foreach ((array) $channelVal as $channelDetailsKey => $channelDetailsVal) {
				if (is_array($channelDetailsVal) == true && $channelDetailsKey == 'item') {
					$msgArr = $channelDetailsVal;
					foreach ((array) $msgArr as $msgKey => $msgVal) {
						$pubDate = $msgVal->pubDate;
						if (strtotime($pubDate) > $timeframe) {
							if ($msgVal->title != '') {
								if ($opencount == 0) {
									$html .= '<div class="blog"><h2>' . $blogTitle . ' - ' . str_replace('www.', '', parse_url($msgVal->link)['host']) . '</h2><ul>';
									$opencount = 1;
								}

								$pubDate2 = strftime('%H:%M', strtotime($pubDate));

								$html .= '<li class="msg">';
								$html .= msgLink($msgVal->link, $pubDate2, $msgVal->title);
								$html .= msgDescription($count = $idTeller, $date = $pubDate, $host = str_replace('www.', '', parse_url($msgVal->link)['host']) . ' - ' . $blogTitle, $title = $msgVal->title, $description = $msgVal->description, $link = $msgVal->link);
								$html .= '</li>';
							}
						}
						$idTeller++;
					}
				}
			}
?>
			</ul>
			</div>
<?php
		}
	}
	return $html;
}
function getArticle($url = false)
{
	if (!$url) return '';
	$lump = file_get_contents($url);
	$start_tag = '"markdown":"';
	$end_tag = '","';

	$startpos = strpos($lump, $start_tag) + strlen($start_tag);
	if ($startpos !== false) {
		$endpos = strpos($lump, $end_tag, $startpos);
		if ($endpos !== false) {
			$html = substr($lump, $startpos, $endpos - $startpos);
		}
	}
	// replaces, approved for hackernoon
	$html = str_replace('\n\n', '</p>', $html); // goed
	$html = str_replace('\\\\\n\u003e', '<p style="border-left:.5em solid yellow;padding-left:3em;font-size:1.25em;font-style:italic;">',  $html); // quote: goed
	$html = str_replace('\\\n### ', '<p class="hekje-3x" style="font-weight:bold;font-size:16px;">', $html); // niet fout
	$html = str_replace('### ', '<p class="hekje-3x" style="font-weight:bold;font-size:16px;">', $html); // niet fout
	$html = str_replace('</p>\\\\\n', '</p><p>', $html); // ruimen
	$html = str_replace('</p>\\\</p>', '</p><p>', $html); // ruimen
	$html = str_replace('</p>\<p', '</p><p', $html); // ruimen
	$html = str_replace('\n', '</p>', $html); // ruimen
	$html = str_replace('![](', '<p style="display:none;">![](', $html); //verbergen
	$html = str_replace('</p>##', '<p style="font-weight:bold;font-size:1.25em">', $html); //verbergen
	$html = str_replace('\\\</p>', '', $html); //verbergen
	$html = str_replace('</p>#<p', '</p><p', $html); //verbergen
	$html = str_replace('</p>#<p', '</p><p', $html); //verbergen
	$html = str_replace('</p><p>[   <p', '</p><p', $html); //verbergen

	return $html;
}

function getFilters()
{
	global $DATA;
	if (isset($_GET['timeframe'])) {
		$getTimeframe = $_GET['timeframe'];
	} else
		$getTimeframe = 36000;
	if (isset($_GET['group'])) {
		$getGroup = $_GET['group'];
	} else
		$getGroup = 'blog';
	$html = '';
	$html .= '<nav>';
	$html .= '<form action="?group=' . (isset($_GET['group']) ? $_GET['group'] : '') . '&timeframe=' . (isset($_GET['timeframe']) ? $_GET['timeframe'] : '') . '">';
	$html .= 'Sorteer:';
	$html .= '<select name="group">';
	$html .= '<option value="blog"' . (($getGroup == 'blog') ? ' selected' : '') . '>Blog</option>';
	$html .= '<option value="datum"' . (($getGroup == 'datum') ? ' selected' : '') . '>Datum</option>';
	$html .= '</select>';
	$html .= 'Tijd:';
	$html .= '<select name="timeframe" id="selectTimeFrame" onChange="javascript:changeVal()">';
	$html .= '<option value="300" ' . (($getTimeframe == 300) ? ' selected' : '') . '>5 minuten</option>';
	$html .= '<option value="600" ' . (($getTimeframe == 600) ? ' selected' : '') . '>10 minuten</option>';
	$html .= '<option value="900" ' . (($getTimeframe == 900) ? ' selected' : '') . '>15 minuten</option>';
	$html .= '<option value="1800" ' . (($getTimeframe == 1800) ? ' selected' : '') . '>30 minuten</option>';
	$html .= '<option value="3600" ' . (($getTimeframe == 3600) ? ' selected' : '') . '>1 uur</option>';
	$html .= '<option value="7200" ' . (($getTimeframe == 7200) ? ' selected' : '') . '>2 uur</option>';
	$html .= '<option value="36000" ' . (($getTimeframe == 36000 || '') ? ' selected' : '') . '>10 uur</option>';
	$html .= '<option value="86400" ' . (($getTimeframe == 86400) ? ' selected' : '') . '>1 dag</option>';
	$html .= '<option value="432000" ' . (($getTimeframe == 432000) ? ' selected' : '') . '>5 dagen</option>';
	$html .= '</select>';
	$html .= 'Ververs: <input type="checkbox" name="refresh" id="refreshTimeFrame" value="' . $getTimeframe . '" ' . ((isset($_GET['refresh']) > 0) ? ' checked' : '') . '/>';
	$html .= '<input type="submit" value="Filter"></input>';

	$html .= '</form>';
	$html .= '</nav>';
	return $html;
}


/*
		"url": "https://github.com/impressivewebs/frontend-feeds#more-front-end-bloggers1",
		https://github.com/impressivewebs/frontend-feeds#top-front-end-bloggers
*/
?>