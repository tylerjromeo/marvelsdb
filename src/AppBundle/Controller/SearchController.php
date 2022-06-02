<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;

class SearchController extends Controller
{

	public static $searchKeys = array(
			''  => 'code',
			'v' => 'flavor',
			'e' => 'pack',
			'f' => 'faction',
			'l' => 'illustrator',
			'k' => 'traits',
			'o' => 'cost',
			'r' => 'date_release',
			'rm' => 'resourceMental',
			'rp' => 'resourcePhysical',
			're' => 'resourceEnergy',
			'rw' => 'resourceWild',
			'at' => 'attack',
			'th' => 'thwart',
			'df' => 'defense',
			'rc' => 'recover',
			't' => 'type',
			'b' => 'subtype',
			'u' => 'isUnique',
			'h' => 'health',
			'x' => 'text',
			'p' => 'xp',
			'qt' => 'quantity',
			'm' => 'set',
			'ed' => 'enemyDamage',
			'eh' => 'enemyHorror',
			'ef' => 'enemyFight',
			'ee' => 'enemyEvade',
			'do' => 'options'
	);

	public static $searchTypes = array(
			'e' => 'code',
			'f' => 'code',
			'y' => 'integer',
			''  => 'string',
			'v' => 'string',
			'k' => 'string',
			'o' => 'integer',
			'r' => 'string',
			't' => 'code',
			'b' => 'code',
			'u' => 'boolean',
			'h' => 'integer',
			's' => 'integer',
			'x' => 'string',
			'p' => 'integer',
			'qt' => 'integer',
			'l' => 'string',
			'rp' => 'integer',
			'rm' => 'integer',
			're' => 'integer',
			'rw' => 'integer',
			'at' => 'integer',
			'th' => 'integer',
			'df' => 'integer',
			'rc' => 'integer',
			'm' => 'code',
			'ed' => 'integer',
			'eh' => 'integer',
			'ee' => 'integer',
			'ee' => 'integer',
			'do' => 'special'
	);

	public function formAction()
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));

		$dbh = $this->getDoctrine()->getConnection();

		//$packs = $this->get('cards_data')->allsetsdata();

		$sets = $this->getDoctrine()->getRepository('AppBundle:Cardset')->findAll();
		$types = $this->getDoctrine()->getRepository('AppBundle:Type')->findAll();
		$packs = $this->getDoctrine()->getRepository('AppBundle:Pack')->findAll();
		$subtypes = $this->getDoctrine()->getRepository('AppBundle:Subtype')->findAll();
		$factions = $this->getDoctrine()->getRepository('AppBundle:Faction')->findAllAndOrderByName();

		$list_traits = $dbh->executeQuery("SELECT DISTINCT c.traits FROM card c WHERE c.traits != ''")->fetchAll();
		//$list_traits = $dbh->executeQuery("SELECT DISTINCT c.content as traits FROM ext_translations c WHERE c.field = 'traits' and c.content != ''")->fetchAll();
		$traits = [];
		foreach($list_traits as $card) {
			// parse traits by looking for period and space, or period and end of the line
			preg_match_all('/(.*?)(?:\. |\.$)/m', $card["traits"], $matches, PREG_SET_ORDER, 0);
			foreach($matches as $sub) {
				if (isset($sub[1])) {
					$traits[trim($sub[1])] = 1;
				}
			}
		}
		$traits = array_filter(array_keys($traits));
		sort($traits);

		$list_illustrators = $dbh->executeQuery("SELECT DISTINCT c.illustrator FROM card c WHERE c.illustrator != '' ORDER BY c.illustrator")->fetchAll();
		$illustrators = array_map(function ($card) {
			return $card["illustrator"];
		}, $list_illustrators);

		$allsets = $this->renderView('AppBundle:Default:allsets.html.twig', [
			"data" => $this->get('cards_data')->allsetsdata(),
		]);

		return $this->render('AppBundle:Search:searchform.html.twig', array(
				"pagetitle" => "Card Search",
				"pagedescription" => "Find all the cards of the game, easily searchable.",
				"packs" => $packs,
				"types" => $types,
				"subtypes" => $subtypes,
				"factions" => $factions,
				"traits" => $traits,
				"sets" => $sets,
				"illustrators" => $illustrators,
				"allsets" => $allsets,
		), $response);
	}

	public function zoomAction($card_code, Request $request)
	{
		$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(array("code" => $card_code));
		if(!$card) throw $this->createNotFoundException('Sorry, this card is not in the database (yet?)');

		$game_name = $this->container->getParameter('game_name');
		$publisher_name = $this->container->getParameter('publisher_name');

		$meta = $card->getName().", a ".$card->getFaction()->getName()." ".$card->getType()->getName()." card for $game_name from the set ".$card->getPack()->getName()." published by $publisher_name.";

		return $this->forward(
			'AppBundle:Search:display',
			array(
			    '_route' => $request->attributes->get('_route'),
			    '_route_params' => $request->attributes->get('_route_params'),
			    'q' => $card->getCode(),
				'view' => 'card',
				'sort' => 'set',
				'pagetitle' => $card->getName(),
				'meta' => $meta
			)
		);
	}

	public function listAction($pack_code, $view, $decks, $sort, $page, Request $request)
	{
		$pack = $this->getDoctrine()->getRepository('AppBundle:Pack')->findOneBy(array("code" => $pack_code));
		if(!$pack) {
			throw $this->createNotFoundException('This pack does not exist');
		}

		$show_spoilers = true;
		if ($request->cookies->get('spoilers') && $request->cookies->get('spoilers') == "show"){
			$show_spoilers = true;
		}

		$game_name = $this->container->getParameter('game_name');
		$publisher_name = $this->container->getParameter('publisher_name');

		$meta = $pack->getName().", a set of cards for $game_name"
				.($pack->getDateRelease() ? " published on ".$pack->getDateRelease()->format('Y/m/d') : "")
				." by $publisher_name.";

		$key = array_search('pack', SearchController::$searchKeys);

		return $this->forward(
			'AppBundle:Search:display',
			array(
			    '_route' => $request->attributes->get('_route'),
			    '_route_params' => $request->attributes->get('_route_params'),
    	        'q' => $key.':'.$pack_code,
				'view' => $view,
				'sort' => $sort,
				'page' => $page,
				'decks' => $decks,
				'pagetitle' => $pack->getName(),
				'meta' => $meta,
				'show_spoilers' => $show_spoilers
			)
		);
	}

	/**
	 * Processes the action of the card search form
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function processAction(Request $request)
	{
		$view = $request->query->get('view') ?: 'list';
		$sort = $request->query->get('sort') ?: 'name';
		$decks = $request->query->get('decks') ?: 'all';

		$operators = array(":","!","<",">");
		$factions = $this->getDoctrine()->getRepository('AppBundle:Faction')->findAll();

		$params = [];
		if($request->query->get('q') != "") {
			$params[] = $request->query->get('q');
		}

		foreach(SearchController::$searchKeys as $key => $searchName) {
			if ($key == "q"){
				continue;
			}
			$val = $request->query->get($key);
			if(isset($val) && $val != "") {
				if(is_array($val)) {
					if($searchName == "faction" && count($val) == count($factions)) continue;
					$params[] = $key.":".implode("|", array_map(function ($s) { return strstr($s, " ") !== FALSE ? "\"$s\"" : $s; }, $val));
				} else {
					if($searchName == "date_release") {
						$op = "";
					} else {
						if(!preg_match('/^[\p{L}\p{N}\.\_\-\&]+$/u', $val, $match)) {
							$val = "\"$val\"";
						}
						$op = $request->query->get($key."o");
						if(!in_array($op, $operators)) {
							$op = ":";
						}
					}
					$params[] = "$key$op$val";
				}
			}
		}
		$find = array('q' => implode(" ",$params));
		if($sort != "name") $find['sort'] = $sort;
		if($view != "list") $find['view'] = $view;
		if($decks != "all") $find['decks'] = $decks;

		$response = $this->redirect($this->generateUrl('cards_find').'?'.http_build_query($find));
		return $response;
	}

	/**
	 * Processes the action of the single card search input
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
	 */
	public function findAction(Request $request)
	{
		$q = $request->query->get('q');
		$page = $request->query->get('page') ?: 1;
		$view = $request->query->get('view') ?: 'list';
		$decks = $request->query->get('decks') ?: 'all';
		$sort = $request->query->get('sort') ?: 'name';

		// we may be able to redirect to a better url if the search is on a single set
		$conditions = $this->get('cards_data')->syntax($q);
		if(count($conditions) == 1 && count($conditions[0]) == 3 && $conditions[0][1] == ":") {
		    if($conditions[0][0] == array_search('pack', SearchController::$searchKeys)) {
		        $url = $this->get('router')->generate('cards_list', array('pack_code' => $conditions[0][2], 'view' => $view, 'decks' => $decks, 'sort' => $sort, 'page' => $page));
		        return $this->redirect($url);
		    }
		}

		$response = $this->forward(
			'AppBundle:Search:display',
			array(
				'q' => $q,
				'view' => $view,
				'decks' => $decks,
				'sort' => $sort,
				'page' => $page,
				'_route' => $request->get('_route')
			)
		);

		return $response;
	}

	public function displayAction($q, $view="card", $decks="all", $sort, $page=1, $pagetitle="", $meta="", Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));

		static $availability = [];

		$show_spoilers = 1;
		if ($request->cookies->get('spoilers') && $request->cookies->get('spoilers') == "show"){
			$show_spoilers = 1;
		}


		$cards = [];
		$first = 0;
		$last = 0;
		$pagination = '';

		$pagesizes = array(
			'list' => 240,
			'spoiler' => 240,
			'card' => 20,
			'scan' => 20,
			'short' => 1000,
		);
		$includeReviews = FALSE;

		if(!array_key_exists($view, $pagesizes))
		{
			$view = 'list';
		}

		$conditions = $this->get('cards_data')->syntax($q);
		$conditions = $this->get('cards_data')->validateConditions($conditions);

		// reconstruction de la bonne chaine de recherche pour affichage
		$q = $this->get('cards_data')->buildQueryFromConditions($conditions);
		$include_encounter = false;
		$include_encounter = true;
		$spoiler_protection = true;

		if ($decks == "encounter"){
			$include_encounter = "encounter";
		}
		if ($decks == "player"){
			$include_encounter = false;
		}

		if($q && $rows = $this->get('cards_data')->get_search_rows($conditions, $sort, false, $include_encounter))
		{
			if(count($rows) == 1)
			{
				$view = 'card';
				$includeReviews = TRUE;
			}

			if($pagetitle == "") {
        		if(count($conditions) == 1 && count($conditions[0]) == 3 && $conditions[0][1] == ":") {
        			if($conditions[0][0] == "e") {
        				$pack = $this->getDoctrine()->getRepository('AppBundle:Pack')->findOneBy(array("code" => $conditions[0][2]));
        				if($pack) $pagetitle = $pack->getName();
        			}
        		}
			}


			// calcul de la pagination
			$nb_per_page = $pagesizes[$view];
			$first = $nb_per_page * ($page - 1);
			if($first > count($rows)) {
				$page = 1;
				$first = 0;
			}
			$last = $first + $nb_per_page;

			// data à passer à la view
			for($rowindex = $first; $rowindex < $last && $rowindex < count($rows); $rowindex++) {
				$card = $rows[$rowindex];
				$pack = $card->getPack();
				$cardinfo = $this->get('cards_data')->getCardInfo($card, false, $spoiler_protection);
				if(empty($availability[$pack->getCode()])) {
					$availability[$pack->getCode()] = false;
					if($pack->getDateRelease() && $pack->getDateRelease() <= new \DateTime()) $availability[$pack->getCode()] = true;
				}
				$cardinfo['available'] = $availability[$pack->getCode()];
				if (isset($cardinfo['linked_card'])){
					$cardinfo['linked_card']['available'] = $availability[$pack->getCode()];
				}
				if($includeReviews) {
				    $cardinfo['reviews'] = $this->get('cards_data')->get_reviews($card);
				    $cardinfo['faqs'] = $this->get('cards_data')->get_faqs($card);
				    //$cardinfo['questions'] = $this->get('cards_data')->get_questions($card);
				    $cardinfo['related'] = $this->get('cards_data')->get_related($card);
				}
				$cards[] = $cardinfo;
			}

			$first += 1;

			// si on a des cartes on affiche une bande de navigation/pagination
			if(count($rows)) {
				if(count($rows) == 1) {
					$pagination = $this->setnavigation($card, $q, $view, $sort, $decks);
				} else {
					$pagination = $this->pagination($nb_per_page, count($rows), $first, $q, $view, $sort, $decks);
				}
			}

			// si on est en vue "short" on casse la liste par tri
			if(count($cards) && $view == "short") {

				$sortfields = array(
					'set' => 'pack_name',
					'name' => 'name',
					'faction' => 'faction_name',
					'type' => 'type_name',
					'cost' => 'cost',
					'strength' => 'strength',
				);

				$brokenlist = [];
				for($i=0; $i<count($cards); $i++) {
					$val = $cards[$i][$sortfields[$sort]];
					if($sort == "name") $val = substr($val, 0, 1);
					if(!isset($brokenlist[$val])) $brokenlist[$val] = [];
					array_push($brokenlist[$val], $cards[$i]);
				}
				$cards = $brokenlist;
			}
		}

		$searchbar = $this->renderView('AppBundle:Search:searchbar.html.twig', array(
			"q" => $q,
			"view" => $view,
			"decks" => $decks,
			"sort" => $sort,
			"show_spoilers" => $show_spoilers
		));

		if(empty($pagetitle)) {
			$pagetitle = $q;
		}

		// attention si $s="short", $cards est un tableau à 2 niveaux au lieu de 1 seul
		return $this->render('AppBundle:Search:display-'.$view.'.html.twig', array(
			"view" => $view,
			"decks" => $decks,
			"sort" => $sort,
			"cards" => $cards,
			"first"=> $first,
			"last" => $last,
			"searchbar" => $searchbar,
			"pagination" => $pagination,
			"pagetitle" => $pagetitle,
			"metadescription" => $meta,
			"includeReviews" => $includeReviews,
			"show_spoilers" => $show_spoilers
		), $response);
	}

	public function setnavigation($card, $q, $view, $sort, $encounter)
	{
	    $em = $this->getDoctrine();
	    $prev = $em->getRepository('AppBundle:Card')->findOneBy(array("pack" => $card->getPack(), "position" => $card->getPosition()-1));
	    $next = $em->getRepository('AppBundle:Card')->findOneBy(array("pack" => $card->getPack(), "position" => $card->getPosition()+1));
	    return $this->renderView('AppBundle:Search:setnavigation.html.twig', array(
	            "prevtitle" => $prev ? $prev->getName() : "",
	            "prevhref" => $prev ? $this->get('router')->generate('cards_zoom', array('card_code' => $prev->getCode())) : "",
	            "nexttitle" => $next ? $next->getName() : "",
	            "nexthref" => $next ? $this->get('router')->generate('cards_zoom', array('card_code' => $next->getCode())) : "",
	            "settitle" => $card->getPack()->getName(),
	            "sethref" => $this->get('router')->generate('cards_list', array('pack_code' => $card->getPack()->getCode())),
	    ));
	}

	public function paginationItem($q = null, $v, $s, $e, $ps, $pi, $total)
	{
		return $this->renderView('AppBundle:Search:paginationitem.html.twig', array(
			"href" => $q == null ? "" : $this->get('router')->generate('cards_find', array('q' => $q, 'view' => $v, 'sort' => $s, 'decks' => $e, 'page' => $pi)),
			"ps" => $ps,
			"pi" => $pi,
			"s" => $ps*($pi-1)+1,
			"e" => min($ps*$pi, $total),
		));
	}

	public function pagination($pagesize, $total, $current, $q, $view, $sort, $encounter)
	{
		if($total < $pagesize) {
			$pagesize = $total;
		}

		$pagecount = ceil($total / $pagesize);
		$pageindex = ceil($current / $pagesize); #1-based

		$first = "";
		if($pageindex > 2) {
			$first = $this->paginationItem($q, $view, $sort, $encounter, $pagesize, 1, $total);
		}

		$prev = "";
		if($pageindex > 1) {
			$prev = $this->paginationItem($q, $view, $sort, $encounter, $pagesize, $pageindex - 1, $total);
		}

		$current = $this->paginationItem(null, $view, $sort, $encounter, $pagesize, $pageindex, $total);

		$next = "";
		if($pageindex < $pagecount) {
			$next = $this->paginationItem($q, $view, $sort, $encounter, $pagesize, $pageindex + 1, $total);
		}

		$last = "";
		if($pageindex < $pagecount - 1) {
			$last = $this->paginationItem($q, $view, $sort, $encounter, $pagesize, $pagecount, $total);
		}

		return $this->renderView('AppBundle:Search:pagination.html.twig', array(
			"first" => $first,
			"prev" => $prev,
			"current" => $current,
			"next" => $next,
			"last" => $last,
			"count" => $total,
			"ellipsisbefore" => $pageindex > 3,
			"ellipsisafter" => $pageindex < $pagecount - 2,
		));
	}

}
