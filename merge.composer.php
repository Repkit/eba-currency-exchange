<?php 

$composer1 = $argv[1];
$composer2 = $argv[2];

$data1 = json_decode(file_get_contents($composer1), true);
$data2 = json_decode(file_get_contents($composer2), true);

$data2['require'] = array_merge($data1['require'], $data2['require']);
$data2['require'] = array_merge($data1['require'], $data2['require']);

if( isset($data1['repositories']) && !empty($data1['repositories']) )
{
	if( !isset($data2['repositories']) )
	{
		$data2['repositories'] = $data1['repositories'];
	}
	else
	{
		foreach ($data1['repositories'] as $repository) 
		{
			$data2['repositories'][] = $repository;
		}
	}
}

$data2['require-dev'] = null;
unset($data2['require-dev']);

copy($composer2, $composer2.'.bak');

file_put_contents($composer2, json_encode($data2));