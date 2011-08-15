<?php
//require the php OAuth library
require_once "lib/oauth.php";

class MyOAuthSignatureMethod_RSA_SHA1 extends OAuthSignatureMethod_RSA_SHA1 {
	protected function fetch_public_cert(&$request) {
	    $s = curl_init();
		curl_setopt($s,CURLOPT_URL,$_GET['xoauth_signature_publickey']);
		curl_setopt($s, CURLOPT_RETURNTRANSFER, 1);
		$cert = curl_exec($s);
		curl_close($s);
		return $cert;
	}
	protected function fetch_private_cert(&$request) {
		return;
	}
}

$request = OAuthRequest::from_request();
$server = new MyOAuthSignatureMethod_RSA_SHA1();


$return = $server->check_signature($request, null, null, $_GET['oauth_signature']);

if (! $return) {
	die('invalid signature');
}

$data = json_decode(file_get_contents('php://input'), true);

?>
<script xmlns:os="http://ns.opensocial.org/2008/markup" type="text/os-data">
    <os:PeopleRequest key="Viewer" userId="@viewer" fields="name" groupId="@self"/>
</script>
<script type="text/os-template" xmlns:os="http://ns.opensocial.org/2008/markup" require="Viewer">
    Hello ${Viewer.name.givenName} <b>${Viewer.name.familyName}</b>
</script>

<p>Hello <?= $data[0]['result']['displayName'] ?> from Proxied Content.</p>

<script type="text/os-template" xmlns:statusnet="http://statusnet.net.xyz" xmlns:os="http://ns.opensocial.org/2008/markup" require="feed" autoUpdate="true">
    <ul>
        <li repeat="${feed}">
            <statusnet:feedItem showPostLink="true" item="${Cur}"/>
        </li>
    </ul>
</script>

<script type="text/javascript">
    function loadFeed() {
        var fetchData = function() {
            var params = {};
            params[gadgets.io.RequestParameters.AUTHORIZATION]=gadgets.io.AuthorizationType.OAUTH;
            params[gadgets.io.RequestParameters.CONTENT_TYPE] = gadgets.io.ContentType.JSON;
            params[gadgets.io.RequestParameters.OAUTH_SERVICE_NAME]='statusnet';
            gadgets.io.makeRequest('http://status.net.xyz:8061/index.php/api/statuses/home_timeline.json', function(response) {

                if (response.oauthApprovalUrl) {
                    var popup = new gadgets.oauth.Popup(
                        response.oauthApprovalUrl,
                        'width=400&height=400',
                        function() { },
                        function() {
                            fetchData();
                        }
                        );

                    popup.onClick_();
                } else {
                    console.log(response);
                    opensocial.data.DataContext.putDataSet('feed', response.data);
                    bindLinks();
                }
            }, params);
        }

        fetchData();
    }
    
    function bindLinks() {
        $('a.link_post_to_wall').unbind('click').click(function() {
            vz.embed.getEmbedUrl({postId: $(this).attr('id')}, function(embedUrl) {
                var params = [];
                params[opensocial.Message.Field.TYPE] = opensocial.Message.Type.PUBLIC_MESSAGE;
                var message = opensocial.newMessage('Have a look at this update: ' + embedUrl, params);
                var recipient = "VIEWER";
                opensocial.requestSendMessage(recipient, message);
            });
        });
    }
    gadgets.util.registerOnLoadHandler(function() {
        loadFeed();       
    });
</script>