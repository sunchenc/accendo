function	submitcomment()
{	var username=$("#username").val();
	var	email=$("#email").val();
	var	content=$("#comcontent").val();
	var	imgcode=$("#code").val();
	var	articleId=$("#articleId").val();
	if(username==''){
		alert("您的大名不能为空!");
	}else if(email==''){
		alert("电子邮箱不能为空!");
	}else if(imgcode==''){
		alert("验证码不能为空!");
	}else if(content==''){
		alert("请发表点您的评论吧!");
	}else{
	$.post("<{$baseUrl}>/article/comment/",{username:username,articleId:articleId,content:content,imgcode:imgcode,email:email},
			function(data){
				alert(data);
				var   strArray=new   Array();   
				strArray=data.split("||||");
				if(strArray[0]=='1'){
					$("#username").val('');
					$("#email").val('');
					$("#content").val('');
					$("#imgcode").val('');
				}
				
				$("#review").html(strArray[1]);
				
			});
	}
}
	