<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$GLOBALS['adms'] = 'admissao_sistemas_3';
$GLOBALS['amb']  = 'infra_ambiente';
$GLOBALS['url']  = 'http://sistemas.procempa.com.br';


/*
 * ADMISSÂO DE SISTEMAS
*/


$app->get('/adms/id/{id}', function ($id) {
	return (string) file_get_contents("https://webform.procempa.com.br/webform_rest/$GLOBALS[adms]/submission/$id?_format=json");
});



$app->get('/adms/campos', function () {
	$q = "select data 
		  from config 
		  where name ='webform.webform.$GLOBALS[adms]'";
	//echo "$q<br>\n";
	$results = app('db')->select($q);
	return response()->json(yaml_parse(unserialize(stream_get_contents($results[0]->data))['elements']));
});



$app->get('/adms/search/{namer}/{value}/{names}[/{property}]', function ($namer, $value, $names, $property = null) {
	#select delta, name, property, value from webform_submission_data where webform_id = 'admissao_sistemas_3' and name='bd' and sid in (select sid from webform_submission_data where name='bd' and property='bd_nome' and value='PRORAC04') order by delta, property

	if (isset($property))
		$qproperty = " property = '$property' ";
	else
		$qproperty = ' 1=1 ';		

	$value = str_replace("%20", " ", $value);

	$q = "select sid, delta, name, property, value 
		  from webform_submission_data 
		  where webform_id = '$GLOBALS[adms]' 
		    and name='$namer' 
		    and sid in 
		    	 (select sid 
		  	      from webform_submission_data 
		  		  where lower(name)=lower('$names')
		  		    and $qproperty 
		  		    and lower(value)=lower('$value')) 
		  order by sid, delta, property";
	//echo "$q<br>\n";
	$results = app('db')->select($q);
    return response()->json($results);
});





$app->get('/adms/list/{name}[/{property}]', function ($name, $property = null) {
	#postgres=# select sid, name, value from webform_submission_data where webform_id = 'admissao_sistemas_3' and name in ('sigla') order by value;

	if (isset($property))
		$qproperty = " property = '$property' ";
	else
		$qproperty = ' 1=1 ';		

	$q = "select sid, value
		  from webform_submission_data 
		  where webform_id = '$GLOBALS[adms]' 
		    and name = '$name' 
		    and $qproperty
		  order by value";
	//echo "$q<br>\n";
	$results = app('db')->select($q);
    return response()->json($results);
});



$app->get('/adms', function () {
	/*
	$q = "select sid, value as sigla
		  from webform_submission_data 
		  where webform_id = '$GLOBALS[adms]' and name = 'sigla'
		  order by value";
	$reset(array)ults = app('db')->select($q);
    return response()->json($results);
    */	

    //A subquery é necessária porque o campo logo possui um número; tem que resolver para a URI
    $q = "select * ,
			(select f.uri
			from webform_submission_data s
			left join file_managed f on f.fid = NULLIF(s.value, '')::int
			where name = 'logo' and s.sid = a.sid
			) as uri
		  from webform_submission_data a
		  where webform_id= 'admissao_sistemas_3'
		  order by sid, delta, name, property";
	//echo "$q<br>\n";
	$results = app('db')->select($q);
	//print_r($results); //die;

	foreach ($results as $r) {
		$dados[$r->sid]['sid'] = $r->sid;
		if ($r->property == '') {
			$dados[$r->sid][$r->name] = $r->value;
			if ($r->uri != '') {
				$dados[$r->sid]['uri'] = $r->uri;
			}

		}
		else
			$dados[$r->sid][$r->name][$r->delta][$r->property] = $r->value;
	}
	//print_r($dados); die;

	foreach ($dados as $k=>$d) {
		$resp[$k]['sid'] = $d['sid'];
		$resp[$k]['sigla'] = $d['sigla'];
		if (isset($d['uri'])) {
			//$resp[$k]['uri'] = $d['uri'];
			//private:\/\/webform\/admissao_sistemas_3\/123\/RStudio-Ball_0.png
			//https://sistemas.procempa.com.br/system/files/webform/admissao_sistemas_3/123/RStudio-Ball_0.png

			//https://sistemas.procempa.com.br/sites/default/files/webform/admissao_sistemas_3/79/Asm_logo.png			
			$uri = str_replace('private://', 'system/files/', $d['uri']);
			$uri = str_replace('public://', 'sites/default/files/', $uri);
			//echo "$d[sid]: $uri<br>\n";
			$resp[$k]['logo'] = "$GLOBALS[url]/$uri";
			//echo "$d[sid]: " .$resp[$k]['uri'] ."<br>\n";
		}
		else
			$resp[$k]['uri'] = '';

		//URLs
		if (isset($d['url'])) {
			$resp[$k]['url'] = '';
			foreach ($d['url'] as $url)
				$resp[$k]['url'] .= $url['url_nome'] .': ' .$url['url_url'] .", ";
		}

		//Módulos Infra
		if (isset($d['aplicacao'])) {
			unset($m);
			foreach ($d['aplicacao'] as $ap)
				$m[$ap['aplic_modulo']] = $ap['aplic_modulo'];
		}
		$resp[$k]['modulos'] = implode(', ', $m);


		//Tipo Aplicação
		if (isset($d['aplicacao'])) {
			unset($m);
			foreach ($d['aplicacao'] as $ap)
				$m[$ap['aplic_tipo']] = $ap['aplic_tipo'];
		}
		$resp[$k]['infra_aplicacao'] = implode(', ', $m);


		//Tipo Banco
		if (isset($d['bd'])) {
			unset($m);
			foreach ($d['bd'] as $bd)
				$m[$bd['bd_tipo']] = $bd['bd_tipo'];
		}
		$resp[$k]['infra_bd'] = implode(', ', $m);

	}
	//print_r($resp);

    //return response()->json($results);
    return response()->json($resp);
    				 //->header('Access-Control-Allow-Origin', '*');
});






/*
 * AMBIENTES
*/


$app->get('/amb/id/{id}', function ($id) {
	return (string) file_get_contents("https://webform.procempa.com.br/webform_rest/$GLOBALS[amb]/submission/$id?_format=json");
});


$app->get('/amb', function () {
	$q = "select sid, 
				 value as sigla, 
				 (select string_agg(value , ', ')  from webform_submission_data where webform_id= 'infra_ambiente' and name = 'sinonimo' 
					 and property='sinonimo' and sid=a.sid) as sinonimo,
				(select string_agg(value , ', ') from webform_submission_data where webform_id= '$GLOBALS[amb]' and name = 'servidor' and property='serv_nome' and sid=a.sid) as servidores
		  from webform_submission_data a
		  where webform_id= '$GLOBALS[amb]'
  		    and name = 'nome'
		  order by value";
	//echo "$q<br>\n";
	$results = app('db')->select($q);
    return response()->json($results);

});





/*
 * TESTES
*/



$app->get('/teste', function () {
	$q = "select sid, name, value 
		  from webform_submission_data 
		  where webform_id = '$GLOBALS[adms]'";
	$results = app('db')->select($q);
    //return (string)print_r($results);
    return response()->json($results);
});


$app->get('/', function () use ($app) {
    return $app->version();
});

$app->get('/phpinfo', function () {
    return (string)phpinfo();
});


