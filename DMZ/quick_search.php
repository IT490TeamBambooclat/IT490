<?php
$letter = $argv[1] ?? '';
if (!preg_match('/^[a-zA-Z]$/', $letter)) {
    fwrite(STDERR, "Usage: php quick_search.php <letter>\n");
    exit(1);
}
$API_HOST="https://data.usajobs.gov/api/Search";
$API_KEY="JARdgfQahwqDDdgixRjy/i7LyfIoEhmnJhwt9duouWM=";
$USER_AGENT="teambamboclaat@gmail.com";
$url=$API_HOST."?Keyword=".urlencode($letter)."&ResultsPerPage=10";
$ch=curl_init($url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_HTTPHEADER,[
"Host: data.usajobs.gov",
"User-Agent: ".$USER_AGENT,
"Authorization-Key: ".$API_KEY
]);
$response=curl_exec($ch);
$http_code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);
if($http_code!==200||!$response){
    fwrite(STDERR,"API request failed (HTTP $http_code)\n");
    exit(1);
}
$data=json_decode($response,true);
$items=$data['SearchResult']['SearchResultItems']??[];
if(!$items){
    echo "No results for '$letter'.\n";
    exit(0);
}
echo "Top results for '$letter':\n";
$i=1;
foreach($items as $item){
    $d=$item['MatchedObjectDescriptor']??[];
    $title=$d['PositionTitle']??'N/A';
    $org=$d['OrganizationName']??'N/A';
    $locs=array_column($d['PositionLocations']??[],'LocationName');
    $loc=$locs?implode(', ',$locs):'N/A';
    $date=$d['PublicationStartDate']??'N/A';
    printf("%2d) %s — %s — %s (Posted: %s)\n",$i++,$title,$org,$loc,$date);
}

