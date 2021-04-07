{
	"translatorID": "0b7904b6-8484-15d1-5fd3-9140a7efb3e9",
	"label": "Haute autorité de santé",
	"creator": "Alexandre BRULET",
	"target": "^https:?//www\\.has-sante\\.fr",
	"minVersion": "3.0",
	"maxVersion": "",
	"priority": 100,
	"inRepository": false,
	"translatorType": 4,
	"browserSupport": "gcsibv",
	"lastUpdated": "2020-03-21 12:49:21"
}
/*
  ***** BEGIN LICENSE BLOCK *****

  Copyright © 2020 Alexandre BRULET

  This file is part of Zotero.

  Zotero is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Zotero is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with Zotero. If not, see <http://www.gnu.org/licenses/>.

  ***** END LICENSE BLOCK *****
*/

function attr(docOrElem, selector, attr, index) {
  var elem = index ? docOrElem.querySelectorAll(selector).item(index) : docOrElem.querySelector(selector);
  return elem ? elem.getAttribute(attr) : null;
}

function text(docOrElem, selector, index) {
  var elem = index ? docOrElem.querySelectorAll(selector).item(index) : docOrElem.querySelector(selector);
  return elem ? elem.textContent : null;
}

function detectWeb(doc, url) {
    // Adjust the inspection of url as required
  if (url.includes('resultat-de-recherche') != -1 && getSearchResults(doc, true)) {
    return 'multiple';
  }
  else if (url.indexOf('has-sante.fr/jcms/') != -1){
    return 'document';
  }
  // Add other cases if needed
}

//look for multiple results
function getSearchResults(doc, checkOnly) {
  var items = {};
  var found = false;
  // Adjust the CSS Selectors
  var rows = doc.querySelectorAll('.clusterSearch .content .title a');
  for (var i=0; i<rows.length; i++) {
    // Adjust if required, use Zotero.debug(rows) to check
    var href = rows[i].href;
    // Adjust if required, use Zotero.debug(rows) to check
    var title = ZU.trimInternal(rows[i].textContent);
    if (!href || !title) continue;
    if (checkOnly) return true;
    found = true;
    items[href] = title;
  }
  return found ? items : false;
}

function doWeb(doc, url) {
  var detect = detectWeb(doc, url);
  if ( detect == "multiple") {
    Zotero.selectItems(getSearchResults(doc, false), function (items) {
      if (!items) {
        return true;
      }
      var articles = [];
      for (var i in items) {
        articles.push(i);
      }
      ZU.processDocuments(articles, scrape);
    });
  } else if (detect == "document"){
    scrape(doc, url);
  }
}

function scrape(doc, url, additionnalItems) {
  if (additionnalItems === undefined) {
    additionnalItems = false;
  }
	var translator = Zotero.loadTranslator('web');
	// Embedded Metadata
	translator.setTranslator('951c027d-74ac-47d4-a107-9c3069ab7b48');
	// translator.setDocument(doc);

	translator.setHandler('itemDone', function (obj, item) {
		///improve author parsing
		//item.creators = [];
		//var authors = attr(doc, 'meta[name=author]', 'content');
		//authors = authors.split(/\s*,\s/);
		///Z.debug(authors)
		//for (let author of authors) {
		//	if (author !== "Agencias") {
		//		item.creators.push(ZU.cleanAuthor(author, "author"));
		//	}
		//}
		item.publisher = "HAS";
    		item.abstractNote = text(doc, '.encart p.first');
		item.date = text(doc, 'li.validation span.val');
		map = new Map([
			['Recommandation de bonne pratique', 'practice guidelines']
		])
		temp = text(doc, '.sub-title .titleLabelCaps')
		for (let [key, value] of map) {
			if (temp == key)
				item.extra = "ebmType: " + value;
			break;
		}
		//if (item.section) {
		//	item.section = ZU.capitalizeTitle(item.section.replace(/_/, " "), true);
		//}
		item.complete();
	});
	translator.getTranslatorObject(function(trans) {
		trans.itemType = "document";
		trans.doWeb(doc, url);
	});
}

// Test cases
var testCases = [{
  "type": "web",
  "url": "https://www.has-sante.fr/jcms/c_1022476/fr/strategie-medicamenteuse-du-controle-glycemique-du-diabete-de-type-2",
  "items": [{
    "itemType": "document",
    "creators": [],
    "title": "Stratégie médicamenteuse du contrôle glycémique du diabète de type 2",
    "abstractNote": "L’objectif de ce travail est d’améliorer la qualité de la prise en charge des patients adultes atteints d’un diabète de type 2 ; seul le contrôle glycémique est abordé dans cette recommandation.",
    "language": "fr",
    "libraryCatalog": "www.has-sante.fr",
    "accessDate": "CURRENT_TIMESTAMP",
    "publisher": "HAS",
    "date": "09 janvier 2013",
    "extra": "ebmType: Recommandation de bonne pratique",
    "notes": [],
    "tags": [
        "recommandations",
        "évaluation",
        "certification établissements",
        "accréditation médecins",
        "pratiques professionnelles",
        "soins médicaux",
        "ALD",
        "service médical rendu",
        "médicaments",
        "santé",
        "has",
        "haute autorité de santé",
        "certification",
        "assurance maladie",
        "patient",
        "qualité soins",
        "commission transparence",
        "Traitement médicamenteux",
        "Diabète",
        "Recommandations de bonne pratique",
        "Toutes nos publications",
        "Économie de la santé"
    ],
    "seeAlso": [],
    "attachments": [{
            "title": "Snapshot",
            "url": "https://www.has-sante.fr/jcms/c_1022476/fr/strategie-medicamenteuse-du-controle-glycemique-du-diabete-de-type-2",
            "mimeType": "text/html"
        }]
  }]
}]
