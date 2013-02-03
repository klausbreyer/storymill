<?php
// include	"helper.php";
$client_id = "";
$client_secret = "";
$redirect_uri = "https://storymill.cloudcontrolled.com/";
// set access token from readmill
$auth_uri = "https://readmill.com/oauth/authorize?response_type=code&client_id=$client_id&redirect_uri=$redirect_uri&scope=non-expiring";
$access_token = get_access_token($authCode); // $authCode is set by from the GET variable code: $authCode = $_GET['code'];


function api_request($url) {

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_URL,$url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);

	$output = curl_exec($ch);
	curl_close($ch);
	$requestedObj = json_decode($output);

	return $requestedObj;
}
 
/**
* dissociated-press.php
*
* @author     David Pascoe-Deslauriers <dpascoed@csiuo.com>
* @copyright  2009 David Pascoe-Deslauriers
* @license    http://www.csiuo.com/license/bsd.html  Simplified BSD License
* @link       http://www.csiuo.com/drupal/node/13
*/
 
 
class dissociatedpress {
 
	function dissociate ($str, $randomstart = true, $groupsize = 4, $max = 128) {
		if ($groupsize < 2) {
			$groupsize = 2;
		}
		// Capitalize the first word
		$capital = true;
 
		//Remove from corpus, they just make the result confusing
		$str = str_replace(array("(",")","[","]","{","}"), array(),$str);
 
		//Break up tokens
		$tokens = preg_split("/[ \r\n\t]/",$str);
 
		//Clean up token array
		for ($i = 0; $i < sizeof($tokens); $i++){
			if ($tokens[$i] == ""){
				unset($tokens[$i]);
			}
		}
 
		$tokens = array_values($tokens);
 
		//Init variables
		$return = "";
		$lastmatch = array();
 
		// if we start at the beginning, start there
		if (!$randomstart) {
			for ($n = 0; $n < $groupsize; $n++){
				array_push($lastmatch,$tokens[$n]);
				$res = cleanToken($tokens[$n],$capital);
				$return .= $res[0];
				$capital = $res[1];
			}
		}
 
		//Loop until we have enough output
		$i = 0;
		while ($i < $max + 32){
			// Try and end on a full sentence
			if ($i > $max - 8 and $capital){
				break;
			}
 
			//If the lastmatch group isn't good enough, start randomly
			if (sizeof($lastmatch) < $groupsize){
				$loc = rand(0,sizeof($tokens)-$groupsize);
				$lastmatch = array();
				for ($n = 0; $n < $groupsize; $n++){
					array_push($lastmatch,$tokens[$loc+$n]);
					$res = dissociatedpress::cleanToken($tokens[$loc+$n],$capital);
					$return .= $res[0];
					$capital = $res[1];
				}
			} else {
				$chains = dissociatedpress::findChains($tokens, $lastmatch);
				$lastmatch = array();
 
				// If there aren't enough chains, start randomly next time (avoid getting caught in loops)
				if (sizeof($chains) > 2) {
					$loc = $chains[rand(0, sizeof($chains)-1)];
					for ($n = 0; $n < $groupsize; $n++){
						array_push($lastmatch,$tokens[$loc+$n]);
						$res = dissociatedpress::cleanToken($tokens[$loc+$n],$capital);
						$return .= $res[0];
						$capital = $res[1];
					}
				}
			}
			$i++;
		}
 
		return $return;
	}
 
	/**
	* Join the tokens with proper typography
	*/
 
	function cleanToken($token,$capital) {
		if ($capital){
			$token = ucfirst($token);
			$capital = false;
		}
 
		if (substr($token,-1,1) == '.'){
			$capital = true;
			return array($token . "  ",$capital);
		} else {
			return array($token . " ",$capital);
		}
	}
 
	/**
	* Naively find possible Markov Chains
	*/
 
	function findChains($haystack, $needle) {
		$return = array();
		for ($i = 0; $i < sizeof($haystack) - sizeof($needle); $i++){
			if ($haystack[$i] == $needle[0]){
				$matches = true;
				for ($j = 1; $j < sizeof($needle); $j++){
					if ($haystack[$i+$j] != $needle[$j]){
						$matches = false;
						break;
					}
				}
				if ($matches == true){
					array_push($return,$i+sizeof($needle));
				}
			}
		}
		return $return;
	}
 
}

function get_access_token($authCode) {
	$url = "https://readmill.com/oauth/token.json";
	
	$post_fields .= "grant_type=authorization_code";
	$post_fields .= "&client_id=68cd9fb49055698852730628c51f853e";
	$post_fields .= "&client_secret=031991ff65dcb8b375bfde8c68d1a6ac";
	$post_fields .= "&redirect_uri=https://storymill.cloudcontrolled.com/";
	$post_fields .= "&code=" . $authCode;

	$ch = curl_init();

	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch,CURLOPT_POST,5);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$post_fields);

	$output = curl_exec($ch);
	curl_close($ch);


	$oAuthObj = json_decode($output);
	return $oAuthObj->access_token;
}
$authCode = $_GET['code'];

if ($authCode) {

	$acces_token = get_access_token($authCode);
	if (strlen($acces_token) > 5) {
		setcookie("access_token", $acces_token);
		header("Location: " . $redirect_uri);
	}
}
 
if ($_COOKIE['access_token']) {
	$acces_token = $_COOKIE['access_token'];
	
	$booksURL = "https://api.readmill.com/v2/books?access_token=".$acces_token;
	$books = api_request($booksURL);

	$tit = "";
	$list = "";
	$str = "";
	foreach ($books->items as $key => $value) {

		if ($value->book->language == "en" && $value->book->id != 3) {
			$list .= "<a href='".$value->book->permalink_url ."' title='". $value->book->story ."'>Read: ".  $value->book->title . "</a><br>";
			$str .= 		$value->book->story . " ";
		}
	}
	$pr = dissociatedpress::dissociate($str, true, 4, 16);
}

?>
<!DOCTYPE html>
<html>
<head>

	<title>Storymill</title>
	<style type="text/css" media="screen">
		blockquote {
			font-family: 'Goudy Bookletter 1911', serif;
			font-size:30px;
		}
	</style>

	<!-- Bootstrap -->
	<link href='http://fonts.googleapis.com/css?family=Goudy+Bookletter+1911' rel='stylesheet' type='text/css'>
	<link href="css/bootstrap.min.css" rel="stylesheet" media="screen">
</head>
<body>
	<div class="navbar navbar-inverse navbar-fixed-top"><div class="navbar-inner"><div class="container"><a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse"><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span></a><a class="brand" href="#">Storymill</a><div class="nav-collapse collapse"><ul class="nav"><li class="active">		<a href="<?php echo $auth_uri; ?>">Authenticate</a></li></ul></div></div></div></div>
		
	<div class="container">
		<br><br>	

		<?php if ($acces_token): ?>
			<div class="hero-unit"><h2>We read your last books!</h2>	
				<?php echo $list; ?>
				<h2>We think the summary of your next book is like:</h2>
				<blockquote>	<?php  echo $pr; ?></blockquote>		
					
				<p><a href="/" class="btn btn-primary btn-large">Again &raquo;</a></p>
			</div>
		<?php else: ?>
			<div class="hero-unit"><h1>Welcome to Storymill!</h1>			<p>Storymill is a fun project which read the summaries of your latest read books and creates via markov chains the possible summary of your next book.</p>
				<p><a href="<?php echo $auth_uri; ?>" class="btn btn-primary btn-large">Authenticate &raquo;</a></p>
				<br><br>
				<h2>Example:</h2>
				<p>				<img src="img/ex.png" />		</p>
			</div>
		<?php endif ?>
		<br><br><br>
	</div>
	
	<script src="http://code.jquery.com/jquery-latest.js"></script>
	<script src="js/bootstrap.min.js"></script>
	
	<div id="footer">
		<div class="container">
	<p class="muted credit">Created on #schoenhackday by <a href="http://facebook.com/klausbreyer">Klaus Breyer</a>. Source available <a href="https://github.com/k-b/storymill">here</a>.</p>
	</div></div>
</body>
</html>