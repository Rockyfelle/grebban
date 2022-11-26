<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class GrebbanController extends Controller
{
	public function index(Request $request)
	{

		// Download Raw Products
		$productClient = new Client();
		$productRes = $productClient->get('http://draft.grebban.com/backend/products.json');
		$rawProducts = json_decode($productRes->getBody());

		// Download Raw Categories
		$metaClient = new Client();
		$metaRes = $metaClient->get('http://draft.grebban.com/backend/attribute_meta.json');
		$rawCategories = json_decode($metaRes->getBody());

		// Parse Categories
		$categoriesParsed = [];
		$translation = [];
		foreach ($rawCategories[1]->values as $category) {
			$codeArray = explode('_', $category->code);
			$parentsArray = [];
			foreach ($codeArray as $code) {
				if (is_numeric($code)) $parentsArray[] = $code;
			}
			$categoriesParsed[] = [
				'name' => $category->name,
				'code' => $category->code,
				'parentArray' => $parentsArray,
			];
			$translation[implode('_', $parentsArray)] = $category->name;
		}

		// Parse Colors
		$colorsParsed = [];
		foreach ($rawCategories[0]->values as $color) {
			$colorsParsed[$color->code] = $color->name;
		}

		// Add Paths to Categories
		for ($i = 0; $i < count($categoriesParsed); $i++) {
			$path = '';
			for ($j = 0; $j < count($categoriesParsed[$i]['parentArray']) - 1; $j++) {
				$path .= $translation[$categoriesParsed[$i]['parentArray'][$j]] . ' > ';
			}
			$categoriesParsed[$i]['path'] = $path . $categoriesParsed[$i]['name'];
		}

		// Parse Products
		$productsParsed = [];
		foreach ($rawProducts as $product) {
			$attributes = [];
			$rawColors = explode(',', $product->attributes->color??'');
			if ($rawColors[0] !== '') for ($i = 0; $i < count($rawColors); $i++) {
				$attributes[] = [
					'name' => 'Color',
					'value' => $colorsParsed[$rawColors[$i]] ?? '',
				];
			}

			$cats = explode(',', $product->attributes->cat??'');
			for ($i = 0; $i < count($cats); $i++) {
				//Find category with code matching $cats[$i]
				for ($j = 0; $j < count($categoriesParsed); $j++) {
					if ($categoriesParsed[$j]['code'] == $cats[$i]) {
						$attributes[] = [
							'name' => 'Category',
							'value' => $categoriesParsed[$j]['path']
						];
						break;
					}
				}
			}
			
			$productsParsed[] = [
				'id' => $product->id,
				'name' => $product->name,
				'attributes' => $attributes,
			];
		}

		$paginated = array_chunk($productsParsed, $request['page_size']??10);
		$page = max(1, min(intval($request['page']), count($paginated) - 1));

		return [
			'page' => $page,
			'pages' => count($paginated) - 1,
			'products' => $paginated[$page - 1],
		];
	}
}
