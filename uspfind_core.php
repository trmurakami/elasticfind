<?php
/**
 * Classes file with main functions 
 */


/**
 * Elasticsearch Class
 */
class Elasticsearch
{

    /**
     * Executa o commando get no Elasticsearch
     *
     * @param string   $_id               ID do documento.
     * @param string[] $fields            Informa quais campos o sistema precisa retornar. Se nulo, o sistema retornará tudo.
     * @param string   $alternative_index Caso use indice alternativo
     *
     */
    public static function get($_id, $fields, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["_source"] = $fields;

        $response = $client->get($params);
        return $response;
    }

    /**
     * Executa o commando search no Elasticsearch
     *
     * @param string[] $fields Informa quais campos o sistema precisa retornar. Se nulo, o sistema retornará tudo.
     * @param int      $size   Quantidade de registros nas respostas
     * @param resource $body   Arquivo JSON com os parâmetros das consultas no Elasticsearch
     *
     */
    public static function search($fields, $size, $body, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0 ) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["_source"] = $fields;
        $params["size"] = $size;
        $params["body"] = $body;

        $response = $client->search($params);
        return $response;
    }

    /**
     * Executa o commando update no Elasticsearch
     *
     * @param string   $_id  ID do documento
     * @param resource $body Arquivo JSON com os parâmetros das consultas no Elasticsearch
     *
     */
    public static function update($_id, $body, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["body"] = $body;

        $response = $client->update($params);
        return $response;
    }

    /**
     * Executa o commando delete no Elasticsearch
     *
     * @param string $_id  ID do documento
     *
     */
    public static function delete($_id, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["client"]["ignore"] = 404;

        $response = $client->delete($params);
        return $response;
    }

    /**
     * Executa o commando delete_by_query no Elasticsearch
     *
     * @param string   $_id               ID do documento
     * @param resource $body              Arquivo JSON com os parâmetros das consultas no Elasticsearch
     * @param resource $alternative_index Se tiver indice alternativo
     * 
     * @return array Resposta do comando
     */
    public static function deleteByQuery($_id, $body, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["body"] = $body;

        $response = $client->deleteByQuery($params);
        return $response;
    }

    /**
     * Executa o commando update no Elasticsearch e retorna uma resposta em html
     *
     * @param string   $_id  ID do documento
     * @param resource $body Arquivo JSON com os parâmetros das consultas no Elasticsearch
     *
     */
    static function storeRecord($_id, $body)
    {
        $response = Elasticsearch::elasticUpdate($_id, $body);
        echo '<br/>Resultado: '.($response["_id"]).', '.($response["result"]).', '.($response["_shards"]['successful']).'<br/>';

    }

}

class Requests
{

    static function getParser($get)
    {
        global $antiXss;
        $query = [];

        if (!empty($get['fields'])) {
            $query["query"]["bool"]["must"]["query_string"]["fields"] = $get['fields'];
        } else {
            $query["query"]["bool"]["must"]["query_string"]["default_field"] = "*";
        }

        /* codpes */
        if (!empty($get['codpes'])) {
            $get['search'][] = 'authorUSP.codpes:'.$get['codpes'].'';
        }

        /* Pagination */
        if (isset($get['page'])) {
            $page = $get['page'];
            unset($get['page']);
        } else {
            $page = 1;
        }

        /* Pagination variables */
        $limit = 20;
        $skip = ($page - 1) * $limit;
        $next = ($page + 1);
        $prev = ($page - 1);

        $i_filter = 0;
        if (!empty($get['filter'])) {
            foreach ($get['filter'] as $filter) {
                $filter_array = explode(":", $filter);
                $filter_array_term = str_replace('"', "", (string)$filter_array[1]);
                $query["query"]["bool"]["filter"][$i_filter]["term"][(string)$filter_array[0].".keyword"] = $filter_array_term;
                $i_filter++;
            }

        }

        if (!empty($get['notFilter'])) {
            $i_notFilter = 0;
            foreach ($get['notFilter'] as $notFilter) {
                $notFilterArray = explode(":", $notFilter);
                $notFilterArrayTerm = str_replace('"', "", (string)$notFilterArray[1]);
                $query["query"]["bool"]["must_not"][$i_notFilter]["term"][(string)$notFilterArray[0].".keyword"] = $notFilterArrayTerm;
                $i_notFilter++;
            }
        }

        if (!empty($get['search'])) {

            $resultSearchTermsComplete = [];
            foreach ($get['search'] as $getSearch) {
                if (strpos($getSearch, 'base.keyword') !== false) {
                    $query["query"]["bool"]["filter"][$i_filter]["term"]["base.keyword"] = "Produção científica";
                    $i_filter++;
                } elseif (empty($getSearch)) {
                    $query["query"]["bool"]["must"]["query_string"]["query"] = "*";
                } else {
                    $getSearchClean = $antiXss->xss_clean($getSearch);
                    if (preg_match_all('/"([^"]+)"/', $getSearchClean, $multipleWords)) {
                        //Result is storaged in $multipleWords
                    }
                    $queryRest = preg_replace('/"([^"]+)"/', "", $getSearchClean);
                    $parsedRest = explode(' ', $queryRest);
                    $resultSearchTerms = array_merge($multipleWords[1], $parsedRest);
                    $resultSearchTerms = array_filter($resultSearchTerms);
                    $resultSearchTermsComplete = array_merge($resultSearchTermsComplete, $resultSearchTerms);
                    $getSearchResult = implode("\) AND \(", $resultSearchTermsComplete);
                    $query["query"]["bool"]["must"]["query_string"]["query"] = "\($getSearchResult\)";
                }
            }


        } 

        if (!empty($get['range'])) {
            $query["query"]["bool"]["must"]["query_string"]["query"] = $get['range'][0];
        }         
        
        if (!isset($query["query"]["bool"]["must"]["query_string"]["query"])) {
            $query["query"]["bool"]["must"]["query_string"]["query"] = "*";
        }

        //$query["query"]["bool"]["must"]["query_string"]["default_operator"] = "AND";
        $query["query"]["bool"]["must"]["query_string"]["analyzer"] = "portuguese";
        $query["query"]["bool"]["must"]["query_string"]["phrase_slop"] = 10;
        
        return compact('page', 'query', 'limit', 'skip');
    }

}

class Facets
{
    public function facet($field, $size, $field_name, $sort, $sort_type, $get_search, $open = false)
    {
        $query = $this->query;
        $query["aggs"]["counts"]["terms"]["field"] = "$field.keyword";
        $query["aggs"]["counts"]["terms"]["missing"] = "Não preenchido";
        if (isset($sort)) {
            $query["aggs"]["counts"]["terms"]["order"][$sort_type] = $sort;
        }
        $query["aggs"]["counts"]["terms"]["size"] = $size;

        $response = Elasticsearch::search(null, 0, $query);

        $result_count = count($response["aggregations"]["counts"]["buckets"]);

        echo '<li class="uk-parent '.($open == true ? "uk-open" : "").'">';

        if (($result_count != 0) && ($result_count < 5)) {
            
            echo '<a href="#" style="color:#333">'.$field_name.'</a>';
            echo '<ul class="uk-nav-sub">';
            foreach ($response["aggregations"]["counts"]["buckets"] as $facets) {
                if ($facets['key'] == "Não preenchido") {
                    if (!empty($_SESSION['oauthuserdata'])) {
                        echo '<li>';
                        echo '<div uk-grid>
                                <div class="uk-width-expand" style="color:#333">
                                    <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&search[]=(-_exists_:'.$field.')">'.$facets['key'].'</a>
                                </div>
                                <div class="uk-width-auto" style="color:#333">
                                    <span class="uk-badge" style="font-size:80%">'.number_format($facets['doc_count'], 0, ',', '.').'</span>
                                </div>';
                        echo '</div></li>';
                    }
                } else {                   
                        echo '<li>';
                        echo '<div uk-grid>
                        <div class="uk-width-expand uk-text-small" style="color:#333">
                            <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $facets['key']).'&quot;"  title="E" style="color:#0040ff;font-size: 90%">'.$facets['key'].'</a>
                        </div>
                        <div class="uk-width-auto" style="color:#333">
                            <span class="uk-badge" style="font-size:80%">'.number_format($facets['doc_count'], 0, ',', '.').'</span>
                        </div>
                        <div class="uk-width-auto" style="color:#333">
                            <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&notFilter[]='.$field.':&quot;'.$facets['key'].'&quot;" title="Ocultar">-</a>
                        </div>';
                        echo '</div></li>';
                }

            };
            echo '</ul>';

        } else {
            $i = 0;
            echo '<a href="#" style="color:#333">'.$field_name.'</a>';
            echo ' <ul class="uk-nav-sub">';
            while ($i < 5) {
                if ($response["aggregations"]["counts"]["buckets"][$i]['key'] == "Não preenchido") {
                    if (!empty($_SESSION['oauthuserdata'])) {
                        echo '<li>';
                        echo '<div uk-grid>
                                <div class="uk-width-expand uk-text-small" style="color:#333">
                                    <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&search[]=(-_exists_:'.$field.')">'.$response["aggregations"]["counts"]["buckets"][$i]['key'].'</a>
                                </div>
                                <div class="uk-width-auto" style="color:#333">
                                <span class="uk-badge" style="font-size:80%">'.number_format($response["aggregations"]["counts"]["buckets"][$i]['doc_count'], 0, ',', '.').'</span>
                                </div>';
                        echo '</div></li>';
                        $i++;        
                    }
                } else {
                    echo '<li>';
                    echo '<div uk-grid>
                        <div class="uk-width-expand uk-text-small" style="color:#333">
                            <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $response["aggregations"]["counts"]["buckets"][$i]['key']).'&quot;"  title="E" style="color:#0040ff;font-size: 90%">'.$response["aggregations"]["counts"]["buckets"][$i]['key'].'</a>
                        </div>
                        <div class="uk-width-auto" style="color:#333">
                            <span class="uk-badge" style="font-size:80%">'.number_format($response["aggregations"]["counts"]["buckets"][$i]['doc_count'], 0, ',', '.').'</span>
                        </div>
                        <div class="uk-width-auto" style="color:#333">
                            <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&notFilter[]='.$field.':&quot;'.$response["aggregations"]["counts"]["buckets"][$i]['key'].'&quot;" title="Ocultar">-</a>
                        </div>';
                    echo '</div></li>';
                    $i++;
                }

                
            }

            echo '<a href="#'.str_replace(".", "_", $field).'" style="color:#333" uk-toggle>mais >></a>';
            echo   '</ul></li>';


            echo '
            <div id="'.str_replace(".", "_", $field).'" uk-modal="center: true">
                <div class="uk-modal-dialog">
                    <button class="uk-modal-close-default" type="button" uk-close></button>
                    <div class="uk-modal-header">
                        <h2 class="uk-modal-title">'.$field_name.'</h2>
                    </div>
                    <div class="uk-modal-body">
                    <ul class="uk-list">
            ';

            foreach ($response["aggregations"]["counts"]["buckets"] as $facets) {
                if ($facets['key'] == "Não preenchido") {
                    if (!empty($_SESSION['oauthuserdata'])) {
                        echo '<li>';
                        echo '<div uk-grid>
                            <div class="uk-width-3-3 uk-text-small" style="color:#333"><a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&search[]=-_exists_:'.$field.'">'.$facets['key'].' <span class="uk-badge">'.number_format($facets['doc_count'], 0, ',', '.').'</span></a></div>';
                        echo '</div></li>';
                    }

                } else {
                    if ($facets['key'] == "Não preenchido") {
                        echo '<li>';
                        echo '<div uk-grid>
                            <div class="uk-width-expand uk-text-small" style="color:#333">
                                <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $facets['key']).'&quot;">'.$facets['key'].'</a></div>
                            <div class="uk-width-auto" style="color:#333">
                            <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&notFilter[]='.$field.':&quot;'.$facets['key'].'&quot;">Ocultar</a>
                            ';
                        echo '</div></div></li>';
                    } else {
                        echo '<li><div uk-grid>
                                <div class="uk-width-expand" style="color:#333">
                                    <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $facets['key']).'&quot;">'.$facets['key'].'</a></div>
                                <div class="uk-width-auto" style="color:#333">
                                    <span class="uk-badge">'.number_format($facets['doc_count'], 0, ',', '.').'</span>
                                </div>
                                <div class="uk-width-auto" style="color:#333" uk-tooltip="Ocultar">
                                    <a href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&notFilter[]='.$field.':&quot;'.$facets['key'].'&quot;">-</a>
                                </div>
                            </div>
                            </li>
                            ';
                    }

                }
            };
            echo '</ul>';
            echo '
            </div>
            <div class="uk-modal-footer uk-text-right">
                <button class="uk-button uk-button-default uk-modal-close" type="button">Fechar</button>
            </div>
            </div>
            </div>
            ';

        }
        echo '</li>';

    }

    public function rebuild_facet($field,$size,$nome_do_campo)
    {
        $query = $this->query;
        $query["aggs"]["counts"]["terms"]["field"] = "$field.keyword";
        if (isset($sort)) {
            $query["aggs"]["counts"]["terms"]["order"]["_count"] = "desc";
        }
        $query["aggs"]["counts"]["terms"]["size"] = $size;

        $response = Elasticsearch::elasticSearch(null, 0, $query);

        echo '<li class="uk-parent">';
        echo '<a href="#" style="color:#333">'.$nome_do_campo.'</a>';
        echo ' <ul class="uk-nav-sub">';
        foreach ($response["aggregations"]["counts"]["buckets"] as $facets) {
            $termCleaned = str_replace("&", "*", $facets['key']);
            echo '<li">';
            echo "<div uk-grid>";
            echo '<div class="uk-width-2-3 uk-text-small" style="color:#333">';
            echo '<a href="admin/autoridades.php?term=&quot;'.$termCleaned.'&quot;" style="color:#0040ff;font-size: 90%">'.$termCleaned.' ('.number_format($facets['doc_count'], 0, ',', '.').')</a>';
            echo '</div>';
            echo '</li>';
        };
        echo   '</ul>
          </li>';

    }

    public function facet_range($field,$size,$nome_do_campo,$type_of_number = "")
    {
        $query = $this->query;
        if ($type_of_number == "INT") {
            $query["aggs"]["ranges"]["range"]["field"] = "$field";
            $query["aggs"]["ranges"]["range"]["ranges"][0]["to"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["from"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["to"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["from"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["to"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["from"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 10;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 10;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 20;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 20;
        } else {
            $query["aggs"]["ranges"]["range"]["field"] = "$field";
            $query["aggs"]["ranges"]["range"]["ranges"][0]["to"] = 0.5;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["from"] = 0.5;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["to"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["from"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["to"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["from"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 10;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 10;
        }

        //$query["aggs"]["counts"]["terms"]["size"] = $size;

        $response = Elasticsearch::elasticSearch(null, 0, $query);

        $result_count = count($response["aggregations"]["ranges"]["buckets"]);

        if ($result_count > 0) {
            echo '<li class="uk-parent">';
            echo '<a href="#" style="color:#333">'.$nome_do_campo.'</a>';
            echo ' <ul class="uk-nav-sub">';
            foreach ($response["aggregations"]["ranges"]["buckets"] as $facets) {
                $facets_array = explode("-", $facets['key']);
                echo '<li>
                    <div uk-grid>
                    <div class="uk-width-3-3 uk-text-small" style="color:#333">';
                    echo '<a style="color:#333" href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&search[]='.$field.':['.$facets_array[0].' TO '.$facets_array[1].']">Intervalo '.$facets['key'].' ('.number_format($facets['doc_count'],0,',','.').')</a>';
                    echo '</div>';

                echo '</div></li>';
            };
            echo   '</ul></li>';
        }


    }
}

class Citation
{

    static function getType($material_type)
    {
        switch ($material_type) {
        case "ARTIGO DE JORNAL":
            return "article-newspaper";
        break;
        case "ARTIGO DE PERIODICO":
            return "article-journal";
        break;
        case "PARTE DE MONOGRAFIA/LIVRO":
            return "chapter";
        break;
        case "APRESENTACAO SONORA/CENICA/ENTREVISTA":
            return "interview";
        break;
        case "TRABALHO DE EVENTO-RESUMO":
            return "paper-conference";
        break;
        case "TRABALHO DE EVENTO":
            return "paper-conference";
        break;
        case "TESE":
            return "thesis";
        break;
        case "TEXTO NA WEB":
            return "post-weblog";
        break;
        }
    }

    static function citationQuery($citacao)
    {

        $array_citation = [];
        $array_citation["type"] = Citation::getType($citacao["type"]);
        $array_citation["title"] = $citacao["name"];

        if (!empty($citacao["author"])) {
            $i = 0;
            foreach ($citacao["author"] as $authors) {
                $array_authors = explode(',', $authors["person"]["name"]);
                $array_citation["author"][$i]["family"] = $array_authors[0];
                if (!empty($array_authors[1])) {
                    $array_citation["author"][$i]["given"] = $array_authors[1];
                }
                $i++;
            }
        }

        if (!empty($citacao["isPartOf"]["name"])) {
            $array_citation["container-title"] = $citacao["isPartOf"]["name"];
        }
        if (!empty($citacao["doi"])) {
            $array_citation["DOI"] = $citacao["doi"];
        }
        if (!empty($citacao["url"][0])) {
            $array_citation["URL"] = $citacao["url"][0];
        }
        if ($citacao["base"][0] == "Teses e dissertações") {
            $citacao["publisher"]["organization"]["name"] = "Universidade de São Paulo";
        }

        if (!empty($citacao["publisher"]["organization"]["name"])) {
            $array_citation["publisher"] = $citacao["publisher"]["organization"]["name"];
        }
        if (!empty($citacao["publisher"]["organization"]["location"])) {
            $array_citation["publisher-place"] = $citacao["publisher"]["organization"]["location"];
        }
        if (!empty($citacao["datePublished"])) {
            $array_citation["issued"]["date-parts"][0][] = intval($citacao["datePublished"]);
        }

        if (!empty($citacao["isPartOf"]["USP"]["dados_do_periodico"])) {
            $periodicos_array = explode(",", $citacao["isPartOf"]["USP"]["dados_do_periodico"]);
            foreach ($periodicos_array as $periodicos_array_new) {
                if (strpos($periodicos_array_new, 'v.') !== false) {
                    $array_citation["volume"] = str_replace("v.", "", $periodicos_array_new);
                } elseif (strpos($periodicos_array_new, 'n.') !== false) {
                    $array_citation["issue"] = str_replace("n.", "", $periodicos_array_new);
                } elseif (strpos($periodicos_array_new, 'p.') !== false) {
                    $array_citation["page"] = str_replace("p.", "", $periodicos_array_new);
                }

            }
        }

        $json = json_encode($array_citation);
        $data = json_decode($json);
        return array($data);
    }

}


class UI {
   
    static function pagination($page, $total, $limit, $t)
    {

        echo '<div class="uk-child-width-expand@s uk-grid-divider" uk-grid>';
        echo '<div>';
        echo '<ul class="uk-pagination uk-flex-center">';
        if ($page == 1) {
            echo '<li><a href="#"><span class="uk-margin-small-right" uk-pagination-previous></span> '.$t->gettext('Anterior').'</a></li>';
        } else {
            $_GET["page"] = $page-1 ;
            echo '<li><a href="'.http_build_query($_GET).'"><span class="uk-margin-small-right" uk-pagination-previous></span> '.$t->gettext('Anterior').'</a></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '<div>';
        echo '<p class="uk-text-center">'.$t->gettext('Página ').''.number_format($page, 0, ',', '.') .'</p>';
        echo '</div>';
        echo '<div>';
        echo '<p class="uk-text-center">'.number_format($total, 0, ',', '.') .'&nbsp;'. $t->gettext('registros').'</p>';
        echo '</div>';
        //echo '<div>';
        //if (isset($_GET["sort"])) {
        //    echo '<a href="http://'.$_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'].'?'.str_replace('&sort='.$_GET["sort"].'', "", $_SERVER['QUERY_STRING']).'">'.$t->gettext('Ordenar por Data').'</a>';
        //} else {
        //    echo '<a href="http://'.$_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING'].'&sort=name.keyword">'.$t->gettext('Ordenar por Título').'</a>';
        //}
        //echo '</div>';
        echo '<div>';
        echo '<ul class="uk-pagination uk-flex-center">';
        if ($total/$limit > $page) {
            $_GET["page"] = $page+1;
            echo '<li class="uk-margin-auto-left"><a href="http://'.$_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'].'?'.http_build_query($_GET).'">'.$t->gettext('Próxima').' <span class="uk-margin-small-left" uk-pagination-next></span></a></li>';
        } else {
            echo '<li class="uk-margin-auto-left"><a href="#">'.$t->gettext('Próxima').' <span class="uk-margin-small-left" uk-pagination-next></span></a></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';

    }
}

?>