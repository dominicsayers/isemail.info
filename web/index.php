<?php
require('../vendor/autoload.php');
define('SLASH', '/');

// Deal with $_POST
// If a post request has been received, translate it
// to a REST form
if ((is_array($_POST)) && (array_key_exists('address', $_POST))) {
	$address	= (get_magic_quotes_gpc() === 1) ? stripslashes($_POST['address']) : $_POST['address'];
	header('Location: /' . rawurlencode($address));
//echo 'Location: /' . rawurlencode($_POST['address']); // debug
}

require_once "_system/is_email/is_email.php";
require_once "_system/is_email/test/meta.php";

$methods_simple	= array(	ISEMAIL_META_DESC	=> 'diagnosis',
				ISEMAIL_META_CONSTANT	=> 'constant',
				ISEMAIL_META_CAT_DESC	=> 'category',
				ISEMAIL_META_CATEGORY	=> 'categoryconstant',
				ISEMAIL_META_SMTP	=> 'smtp',
				ISEMAIL_META_VALUE	=> 'numeric',
				ISEMAIL_META_REF_ALT	=> 'reference'
			);

$reserved	= array(	-1			=> 'help',
				-2			=> 'about'
			);

$methods	= $reserved
		+ $methods_simple
		+ array(	-8			=> 'valid',
				-9			=> 'basic',
				-10			=> 'html',
				-11			=> 'json',
				-12			=> 'jsonp',
				-13			=> 'xml'
			);

// Parse request URI
$requestURI	= rawurldecode($_SERVER['REQUEST_URI']);
$delim_pos	= strpos($requestURI, SLASH, 1) ; // First character is always SLASH (we hope)

// URI is in the format
// https://<website>/<method[=value]>/<address> or
// https://<website>/<reserved> or
// https://<website>/<address>
if ($delim_pos === false) {
	$address	= substr($requestURI, 1);

	if (in_array($address, $reserved)) {
		$method		= $address;
		$address	= '';
	} else
		$method		= 'html';
} else {
	$method_value	= substr($requestURI, 1, $delim_pos - 1);
	$address	= substr($requestURI, $delim_pos + 1);
	$equal_pos	= strpos($method_value, '='); // Look for method=value syntax

	if ($equal_pos === false) {
		$method	= $method_value;
		unset($value);
	} else {
		$method	= substr($method_value, 0, $equal_pos++);
		$value	= ($equal_pos === strlen($method_value)) ? '' : substr($method_value, $equal_pos);
	}
}

$method_key	= array_search($method, $methods);

if ($method_key === false) {
	// Even if we have a second slash, we don't recognise the method
	// so assume the slash is part of the address to be tested
	$address	= $method.SLASH.$address;
	$method		= 'html';
}

// Log this request
$log		= $_SERVER['REMOTE_ADDR'] . "\t" . date('H:i:s') . "\t$method\t$address\r\n";
$handle		= @fopen('log/' . date('Y-m-d') . '.log', 'a');

if ($handle !== false) {
	fwrite($handle, $log);
	fclose($handle);
}

// Test the address
$result		= is_email($address, true, true);
$analysis	= is_email_analysis($result, ISEMAIL_META_ALL);

// Output the appropriate bits
if (in_array($method, $methods_simple)) {
	$mime	= 'text/plain';
	$output	= $analysis[$method_key];
} else {
	switch ($method) {
	case 'valid':
		$mime		= 'text/plain';
		$output		= ($result < ISEMAIL_THRESHOLD) ? 1 : 0;
		break;
	case 'basic':
		$mime		= 'text/plain';
		$output		= '<p>' . $analysis[ISEMAIL_META_CAT_DESC] . '</p><p>' . $analysis[ISEMAIL_META_DESC] . '</p>';
		break;
	case 'json':
	case 'jsonp':
		if (isset($value)) {
			$callback1	= "$value(";
			$callback2	= ');';
		} else {
			$callback1	= '';
			$callback2	= '';
		}

		$mime		= 'application/json';
/*
		$address	= addslashes($address);
		$description	= addslashes($analysis[ISEMAIL_META_CAT_DESC]);
		$reference	= addslashes($analysis[ISEMAIL_META_REF_ALT]);
		$output		= <<<JSON
$callback1{
    "address": "$address",
    "{$methods[ISEMAIL_META_DESC]}": "{$analysis[ISEMAIL_META_DESC]}",
    "{$methods[ISEMAIL_META_CONSTANT]}": "{$analysis[ISEMAIL_META_CONSTANT]}",
    "{$methods[ISEMAIL_META_CAT_DESC]}": "$description",
    "{$methods[ISEMAIL_META_CATEGORY]}": "{$analysis[ISEMAIL_META_CATEGORY]}",
    "{$methods[ISEMAIL_META_SMTP]}": "{$analysis[ISEMAIL_META_SMTP]}",
    "{$methods[ISEMAIL_META_VALUE]}": {$analysis[ISEMAIL_META_VALUE]},
    "{$methods[ISEMAIL_META_REF_ALT]}": "$reference"
}$callback2
JSON;
*/
		$json_array = array(
			'address'			=> $address,
			$methods[ISEMAIL_META_DESC]	=> $analysis[ISEMAIL_META_DESC],
			$methods[ISEMAIL_META_CONSTANT]	=> $analysis[ISEMAIL_META_CONSTANT],
			$methods[ISEMAIL_META_CAT_DESC]	=> $analysis[ISEMAIL_META_CAT_DESC],
			$methods[ISEMAIL_META_CATEGORY]	=> $analysis[ISEMAIL_META_CATEGORY],
			$methods[ISEMAIL_META_SMTP]	=> $analysis[ISEMAIL_META_SMTP],
			$methods[ISEMAIL_META_VALUE]	=> $analysis[ISEMAIL_META_VALUE],
			$methods[ISEMAIL_META_REF_ALT]	=> $analysis[ISEMAIL_META_REF_ALT]
		);

		$output		= $callback1 . json_encode($json_array) . $callback2;
		break;
	case 'xml':
		$address	= htmlspecialchars($address);
		$mime		= 'text/xml';
		$output		= <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<isemail>
	<address>$address</address>
	<{$methods[ISEMAIL_META_DESC]}>{$analysis[ISEMAIL_META_DESC]}</{$methods[ISEMAIL_META_DESC]}>
	<{$methods[ISEMAIL_META_CONSTANT]}>{$analysis[ISEMAIL_META_CONSTANT]}</{$methods[ISEMAIL_META_CONSTANT]}>
	<{$methods[ISEMAIL_META_CAT_DESC]}>{$analysis[ISEMAIL_META_CAT_DESC]}</{$methods[ISEMAIL_META_CAT_DESC]}>
	<{$methods[ISEMAIL_META_CATEGORY]}>{$analysis[ISEMAIL_META_CATEGORY]}</{$methods[ISEMAIL_META_CATEGORY]}>
	<{$methods[ISEMAIL_META_SMTP]}>{$analysis[ISEMAIL_META_SMTP]}</{$methods[ISEMAIL_META_SMTP]}>
	<{$methods[ISEMAIL_META_VALUE]}>{$analysis[ISEMAIL_META_VALUE]}</{$methods[ISEMAIL_META_VALUE]}>
	<{$methods[ISEMAIL_META_REF_ALT]}>{$analysis[ISEMAIL_META_REF_ALT]}</{$methods[ISEMAIL_META_REF_ALT]}>
</isemail>
XML;
		break;
	case 'help':
		$host		= $_SERVER['HTTP_HOST'];
		$mime		= 'text/html';
		$html		= <<<HTML
			<article>
				<section>
					<h1>What does this do?</h1>
					<p>This web service tests any email address to see if it complies with the standards set out in the internet RFCs.</p>
					<p><strong>It doesn't check if a particular address actually exists - you can only do that by trying to send an email to it.</strong></p>
					<p>If you want to know more about email address validation, read <a href="/about">the About page</a></p>
				</section>
				<section>
					<h1>How to use the email address validation service</h1>
					<p>The email address validation service has a REST API. The validation results can be returned as semantic HTML, JSON, JSONP or XML.</p>
					<p>In general you would validate an email address like this:
					<strong><code>https://$host/&lt;method&gt;/&lt;address&gt;</code></strong>
					where <code>&lt;method&gt;</code> is one of the methods described below and
					<code>&lt;address&gt;</code> is the address you want to validate.</p>
				</section>
				<section>
					<h2>Default method</h2>
					<p>If you just use <code>https://$host/&lt;address&gt;</code> then the validator
					will return information about <code>&lt;address&gt;</code> in the default HTML format.</p>
					<p>There are some reserved words you can't test like this, for instance
					<code>https://$host/help</code> and <code>https://$host/about</code> and all
					the methods documented below.</p>
				</section>
				<section>
					<h2>Putting an email address into a URL</h2>
					<p>Note the address must be URL-encoded if it contains characters that have
					a special meaning in a URL. In other words, if you try to validate
					<code>a#b@c.com</code> then the <code>#</code> and everything after it will not be forwarded to the server (try it).</p>
					<p>The way to avoid this is to use appropriate URL entities where necessary.
					The address above can be validated by putting <code>a%23b@c.com</code> into the URL.</p>
					<p>If you type an address into the text box then the URL-encoding will be done for you.</p>
				</section>
				<section>
					<h2>Methods</h2>
					<p>Here are the methods you can use</p>
					<dl id="methods">
						<dt>json</dt>
						<dd>e.g. <code>https://$host/json/test@example.com</code></dd>
						<dd>Returns comprehensive information about <code>test@example.com</code> as JSON data.</dd>
						<dt>jsonp=callback</dt>
						<dd>e.g. <code>https://$host/jsonp=myFunction/test@example.com</code></dd>
						<dd>Returns a valid line of Javascript calling the function <code>myFunction</code> with comprehensive information about <code>test@example.com</code>.</dd>
						<dt>xml</dt>
						<dd>e.g. <code>https://$host/xml/test@example.com</code></dd>
						<dd>Returns comprehensive information about <code>test@example.com</code> as XML data.</dd>
						<dt>html</dt>
						<dd>e.g. <code>https://$host/html/test@example.com</code></dd>
						<dd>Returns comprehensive information about <code>test@example.com</code> in HTML format (this is the default).</dd>
						<dt>basic</dt>
						<dd>e.g. <code>https://$host/basic/test@example.com</code></dd>
						<dd>Returns brief, basic information about <code>test@example.com</code> as an HTML fragment.</dd>
						<dt>valid</dt>
						<dd>e.g. <code>https://$host/valid/test@example.com</code></dd>
						<dd>Returns 1 for a valid <a href="https://tools.ietf.org/html/rfc5321#section-4.1.2">RFC 5321 Mailbox</a>, 0 otherwise.</dd>
						<dt>diagnosis</dt>
						<dd>e.g. <code>https://$host/diagnosis/test@example.com</code></dd>
						<dd>Returns the specific diagnostic text for this address.</dd>
						<dt>category</dt>
						<dd>e.g. <code>https://$host/category/test@example.com</code></dd>
						<dd>Returns the general diagnostic category for this address.</dd>
						<dt>numeric</dt>
						<dd>e.g. <code>https://$host/numeric/test@example.com</code></dd>
						<dd>Returns the specific diagnosis for this address as an integer.</dd>
						<dt>constant</dt>
						<dd>e.g. <code>https://$host/constant/test@example.com</code></dd>
						<dd>Returns the specific diagnosis for this address as a constant name.</dd>
						<dt>categoryconstant</dt>
						<dd>e.g. <code>https://$host/categoryconstant/test@example.com</code></dd>
						<dd>Returns the general diagnostic category for this address as a constant name.</dd>
						<dt>smtp</dt>
						<dd>e.g. <code>https://$host/smtp/test@example.com</code></dd>
						<dd>Returns the likely SMTP extended return code for this address as text.</dd>
						<dt>reference</dt>
						<dd>e.g. <code>https://$host/reference/test@example.com</code></dd>
						<dd>Returns any relevant passages from the RFCs that help to support the diagnosis for this address.</dd>
					</dl>
				</section>
				<section>
					<h2>Behaviour note</h2>
					<p>What if you are testing an address that is the same as a method name? Here's what happens:</p>
					<p><code>https://$host/&lt;method&gt;</code> tests <code>&lt;method&gt;</code> as if it was an address.</p>
					<p><code>https://$host/&lt;method&gt;/</code> (note the trailing slash) tests the empty string and returns the results in the format specified by <code>&lt;method&gt;</code>.</p>
				</section>
			</article>
HTML;
		break;
	case 'about':
		$mime		= 'text/html';
		$html		= <<<HTML
			<article>
				<section>
<p>This is an email address validation
service powered by the free PHP function <em><a href="https://github.com/dominicsayers/isemail" TARGET="_blank">is_email()</a></em> created by
<a href="https://dominicsayers.com/">Dominic Sayers</a>.</p>
				</section>
				<section>
<h1>What is a valid email address?</h1>
<p>There's only one real answer to this: a
valid email address is one that you can send emails to.</p>
<p>There are acknowledged standards for
what constitutes a valid email address. These are defined in the
<A HREF="https://en.wikipedia.org/wiki/Request_for_comments" TARGET="_blank">Request
For Comments</A> documents (RFCs) written by the lords of the
internet. These documents are not rules but simply statements of what
some people feel is appropriate behaviour.</p>
<p>Consequently, the people who make email
software have often ignored the RFCs and done their own thing. Thus
it is perfectly possible for you to have been issued an email address
by your <A HREF="https://en.wikipedia.org/wiki/Internet_Service_Provider" TARGET="_blank">internet
service provider</A> (ISP) that flouts the RFC conventions and is in
that sense <I>invalid</I>.</p>
<p>But if your address works then why does
it matter if it's invalid?</p>
<p>That brings us onto the most important
principle in distributed software.</p>
				</section>
				<section>
<h2>The Robustness Principle</h2>
<p>A <A HREF="https://en.wikipedia.org/wiki/Jon_Postel" TARGET="_blank">very
great man</A>, now sadly dead, once <A HREF="https://en.wikipedia.org/wiki/Robustness_principle" TARGET="_blank">said</A></p>
<blockquote><p>be conservative in what you do, be liberal in what you accept from
others</p></blockquote>
<p>
We take this to mean that all messages you send out should conform
carefully to the accepted standards. Messages you receive should be
interpreted as the sender intended so long as the meaning is clear.</p>
<p>
This is a very valuable principle that allows networked software
written by different people at different times to work together. If
we are picky about the standards conformance of other people's work
then we will lose useful functions and services.</p>
<h2>How does this apply to validating email
addresses?</h2>
<p>
If a friend says to you &ldquo;this is my email address&rdquo;
then there's no point saying to her &ldquo;Ah, but it violates RFC
5321&rdquo;. That's not her fault. Her ISP has given her that address
and it works and she's committed to it.</p>
<p>
If you've got an online business that she wants to register for, she
will enter her email address into the registration page. If you then
refuse to create her account on the grounds that her email address is
non-conformant then you've lost a customer. More fool you.</p>
<p>
If she says her address is <code>sally.@herisp.com</code> the chances are
she's typed it in wrong. Maybe she missed off her surname. So there
is a point in validating the address &ndash; you can ask her if she's
sure it's right before you lose her attention and your only mean of
communicating with a potential customer. Most likely she'll say &ldquo;Oh
yes, silly me&rdquo; and correct it.</p>
<p>
Occasionally a user might say &ldquo;Damn right that's my email
address. Quit bugging me and register my account&rdquo;. Better
register the account before you lose a customer, even if it's not a <em>valid</em> email address.</p>
				</section>
				<section>
<h2>Getting it right</h2>
<p>
If you're going to validate an email address you should get it right.
Hardly anybody does.</p>
<p>
The worst error is to reject email addresses that are perfectly
valid. If you have a Gmail account (e.g. <code>sally.phillips@gmail.com</code>)
then you can send emails to <code>sally.phillips+anything@gmail.com</code>.
It will arrive in your inbox perfectly. This is great for registering
with websites because you can see if they've passed your address on
to somebody else when email starts arriving addressed to the unique
address you gave to the website (e.g.
<code>sally.phillips+unique_reference@gmail.com</code>).</p>
<p>
But.</p>
<p>
Sadly, many websites won't let you register an address with a plus
sign in it. Not because they are trying to defeat your tracking
strategy but just because they are crap. They've copied a broken
regular expression from a dodgy website and they are using it to
validate email addresses. And losing customers as a result.</p>
<p>
How long can an email address be? A lot of people say 320 characters.
A lot of people are wrong. <A HREF="https://www.rfc-editor.org/errata_search.php?rfc=3696&amp;eid=1690" TARGET="_blank">It's
254 characters</A>.</p>
<p>
What RFC is the authority for mailbox formats? <A HREF="https://tools.ietf.org/html/rfc822" TARGET="_blank">RFC
822</A>? <A HREF="https://tools.ietf.org/html/rfc2822" TARGET="_blank">RFC
2822</A>? Nope, it's <A HREF="https://tools.ietf.org/html/rfc5321" TARGET="_blank">RFC
5321</A>.</p>
<p>
Getting it right is hard because the RFCs that define the conventions
are trying to serve many masters and they document conventions that
grew up in the early wild west days of email.</p>
<p>
My recommendation is: don't try this yourself. There's free code out
there in many languages that will do this better than anybody's first
attempt. My own first attempt was particularly laughable.</p>
				</section>
				<section>
<h2>Test cases</h2>
<p>
If you do try to write validation code yourself then you should at
least test it. Even if you're adopting somebody else's validator you
should test it.</p>
<p>
To do this you're going to have to write a series of unit tests that
explore all the nooks and crannies of what is allowed by the RFCs.</p>
<p>
Oh wait. You don't have to do that because I've done it for you.</p>
<p>
Packaged along with the free <em>is_email()</em> code is an XML file of 164
unit tests. If you can write a validator that passes all of them:
congratulations, you've done something hard.</p>
<p>
See the tests and the results for <em>is_email()</em> <A HREF="/_system/is_email/test/?all" TARGET="_blank">here</A>.</p>
<p>
If you think any of the test cases is wrong please leave a comment
here.</p>
				</section>
				<section>
<h2>Downloading <em>is_email()</em></h2>
<p>
I've written <em>is_email()</em> as a simple PHP function so it's easy to
include in your project. <A HREF="https://github.com/dominicsayers/isemail/archive/master.zip" TARGET="_blank">Just
download the package here</A>. The tests are included in the package.</p>
				</section>
			</article>
HTML;
		break;
	default: // 'html'
		$mime		= 'text/html';

		// If we haven't asked for an address to be tested then don't give a result
		if ($requestURI === SLASH)
			$html = '';
		else {
			$address	= htmlspecialchars($address);
			$reference	= $analysis[ISEMAIL_META_REF_ALT];
			$method_ref	= $methods[ISEMAIL_META_REF_ALT];
			$reference_html	= ($reference === '') ? '' : "\t\t\t\t\t<p>Here is the relevant passage from the email RFCs: <span id=\"$method_ref\">$reference</span></p>\r\n";

			$html		= <<<HTML
			<article id="results">
				<section class="summary">
					<p>The email address tested was <em><span id="address">$address</span></em></p>
					<p>The general result is: <span id="{$methods[ISEMAIL_META_CAT_DESC]}">{$analysis[ISEMAIL_META_CAT_DESC]}</span></p>
					<p>The specific diagnosis is: <span id="{$methods[ISEMAIL_META_DESC]}">{$analysis[ISEMAIL_META_DESC]}</span></p>
$reference_html				</section>
				<dl class="notes hbox">
					<dt>Category</dt>
					<dd><span id="{$methods[ISEMAIL_META_CATEGORY]}">{$analysis[ISEMAIL_META_CATEGORY]}</span></dd>
					<dt>Diagnosis</dt>
					<dd><span id="{$methods[ISEMAIL_META_CONSTANT]}">{$analysis[ISEMAIL_META_CONSTANT]}</span> (<span id="{$methods[ISEMAIL_META_VALUE]}">{$analysis[ISEMAIL_META_VALUE]}</span>)</dd>
					<dt>SMTP extended code</dt>
					<dd><span id="{$methods[ISEMAIL_META_SMTP]}">{$analysis[ISEMAIL_META_SMTP]}</span></dd>
				</dl>
				</section>
			</article>
HTML;
		}

	}
}

if ($mime !== 'text/html') {
	header("Content-type: $mime");
	echo $output;
	die;	// Don't send HTML below unless asked for
}
?>
<!DOCTYPE html>

<!-- paulirish.com/2008/conditional-stylesheets-vs-css-hacks-answer-neither/ -->
<!--[if lt IE 7 ]> <html lang="en" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <html lang="en" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <html lang="en" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>    <html lang="en" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="en" class="no-js"> <!--<![endif]-->
<head>
	<meta charset="utf-8">

	<!-- Always force latest IE rendering engine (even in intranet) & Chrome Frame
	     Remove this if you use the .htaccess -->
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<title>Is this email address valid?</title>
	<meta name="description" content="A service to determine whether an email address is valid">
	<meta name="author" content="Dominic Sayers">

	<!--  Mobile viewport optimized: j.mp/bplateviewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32">
	<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16">
	<link rel="manifest" href="/manifest.json">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#400300">
	<meta name="theme-color" content="#ffffff">

	<!-- CSS : implied media="all" -->
	<link rel="stylesheet" href="/css/isemail.info.css?v2">

	<!-- All JavaScript at the bottom, except for Modernizr which enables HTML5 elements & feature detects -->
	<script src="/js/libs/modernizr-3.3.1.min.js"></script>
</head>

<body>
	<div id="site" class="vbox">
		<header class="banner hbox toppad">
			<div id="logo" class="leftcol"><a href="/"><img src="/logo.png" /></a></div>
			<h1 class="boxFlex">Email address validation</h1>
			<div class="vbox">
				<div class="boxFlex">&nbsp;</div>
				<menu id="sitenav" class="hbox">
					<li><a href="/">Home</a></li>
					<li><a href="https://github.com/dominicsayers/isemail/archive/master.zip">Download</a></li>
					<li><a href="https://blog.dominicsayers.com" target="_blank">Blog</a></li>
					<li><a href="/help">Help</a></li>
					<li><a href="/about">About</a></li>
					<li><form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="5E3PPJVR2VE3G">
<input type="image" src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_GB/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online.">
<img alt="" border="0" src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_GB/i/scr/pixel.gif" width="1" height="1">
</form></li>

				</menu>
			</div>
		</header>

		<div id="app" class="boxFlex">
			<div class="constrained_width"> <!-- Mozilla needs inner div or else the whole page goes narrow -->
<?php echo $html; ?>
				<div class="form">
					<form method="post" action="/index.php" class="hbox">
						<p><label>Email address: <input name="address" size="64" value="<?php echo $address ?>" /></label></p>
						<p><button type="submit">Test</button></p>
					</form>
				</div>
			</div>
		</div>

		<div id="comments"> <!-- Mozilla needs inner div or else the whole page goes narrow -->
			<div id="disqus_thread"></div>
		</div>

		<footer class="banner">
			<menu>
				<li><a href="https://github.com/dominicsayers/isemail/archive/master.zip">Download</a></li>
				<li><a href="https://blog.dominicsayers.com" target="_blank">Blog</a></li>
				<li><a href="/help">Help</a></li>
				<li><a href="/about">About</a></li>
				<li><a href="https://tools.ietf.org/html/rfc5321" target="_blank">RFC 5321</a></li>
				<li><a href="https://tools.ietf.org/html/rfc5322" target="_blank">RFC 5322</a></li>
				<li><a href="https://tools.ietf.org/html/rfc5336" target="_blank">RFC 5336</a></li>
				<li><a href="https://tools.ietf.org/html/rfc5952" target="_blank">RFC 5952</a></li>
				<li><a href="https://tools.ietf.org/html/rfc4291" target="_blank">RFC 4291</a></li>
				<li><a href="https://tools.ietf.org/html/rfc1123" target="_blank">RFC 1123</a></li>
				<li>&copy; 2016 <a href="https://dominicsayers.com" target="_blank">Dominic Sayers</a></li>
				<li>Powered by <a href="https://github.com/dominicsayers/isemail" target="_blank">is_email()</a></li>
				<li><form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="5E3PPJVR2VE3G">
<input type="image" src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_GB/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online.">
<img alt="" border="0" src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_GB/i/scr/pixel.gif" width="1" height="1">
</form></li>
			</menu>
		</footer>
	</div> <!--! end of #site -->

	<!-- Javascript at the bottom for fast page loading -->
	<!-- Grab Google CDN's jQuery. fall back to local if necessary -->
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.js"></script>
	<script>!window.jQuery && document.write(unescape('%3Cscript src="/js/libs/jquery-1.4.2.js"%3E%3C/script%3E'))</script>

	<!-- scripts concatenated and minified via ant build script-->
	<script src="/js/plugins.js"></script>
	<script src="/js/script.js"></script>
	<!-- end concatenated and minified scripts-->

	<!-- Disqus -->
	<script>
		var disqus_config = function () {
			this.page.url = 'https://isemail.info';
			this.page.identifier = 'isemail.info';
		};

		(function() { // DON'T EDIT BELOW THIS LINE
		var d = document, s = d.createElement('script');
		s.src = '//isemail-info.disqus.com/embed.js';
		s.setAttribute('data-timestamp', +new Date());
		(d.head || d.body).appendChild(s);
		})();
	</script>
	<noscript>Please enable JavaScript to view the <a href="https://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>

	<!-- asynchronous google analytics: mathiasbynens.be/notes/async-analytics-snippet
	     change the UA-XXXXX-X to be your site's ID -->
	<script>
		var _gaq = [['_setAccount', 'UA-23828714-1'], ['_trackPageview']];

		(function(d, t) {
			var	g = d.createElement(t),
				s = d.getElementsByTagName(t)[0];
				g.async = true;

			g.src = ('https:' == location.protocol ? 'https://ssl' : 'https://www') + '.google-analytics.com/ga.js';
			s.parentNode.insertBefore(g, s);
		})(document, 'script');
	</script>
</body>
</html>
