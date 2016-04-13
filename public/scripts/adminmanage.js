//上傳的JS代碼
function uploadImg(id,fileid,formid){
	var formids=formid;
	var formid="#"+formid;
	
	var filepath=$('#uploadfile').val();
	  	if((filepath.indexOf("."))!==-1)  
	  	{		var get=filepath.lastIndexOf(".")+1;  
	         	var filetype=filepath.substring(get,filepath.length); 
	         	var filetype=filetype.toLowerCase();
	  	}
	  	var allow="jpg,gif,bmp,jpeg,png";
		var result=allow.indexOf(filetype);
		
	if(result>=0){
		 oldTarget=$(formid).attr('target');
		 oldAction=$(formid).attr('action');
		 oldOnsubmit=$(formid).attr('onsubmit');
		 $(formid).attr('target','uploadImgFrame');
		 $(formid).attr('action','<{$baseUrl}>/admin/article/upload/fileid/'+fileid);
		 $(formid).attr('onsubmit','');
		
		 $(formid).submit();
		 $(formid).attr('target',oldTarget);
		 $(formid).attr('action',oldAction);
		 $(formid).attr('onsubmit',oldOnsubmit);
		var ids="#fileupload"+id;
		var id=parseFloat(id);
		var cid=id+1;
		var changeid="fileupload"+cid;
		var filed="<p id="+changeid+"><input type=\"file\"  name=\"uploadfile[]\" size=\"30\" id=\"uploadfile\" />图片说明:<input name=\"attachintro[]\" size=\"50\" type=\"text\"><input type=\"button\" name=\"Submit"+cid+"\"  onclick=\"uploadImg('"+cid+"','"+changeid+"','"+formids+"');\" value=\"上传\" /></p>";
		$(ids).after(filed);

	  }else{
		 	alert("对不起,您选择的上传文件不是有效的图片文件！");
		 	return false;
	  }
}

//fck插入图片函数
function InserImg(url){
	var oEditor = FCKeditorAPI.GetInstance('FCKcontent');
	oEditor.InsertHtml("<img src='"+url+"'>");
}


function delAttachment(id,imgname,typeid){
	if(confirm("確定要刪除嗎?")){
	//alert(id);	
	$.post('<{$baseUrl}>/admin/article/delattach/',{'id':id,'imgname':imgname,'typeid':typeid}, function(data) {
	$("#attachment").empty();
	//alert(data);
	$("#attachment").html(data);
	});
	}	
	else{
		return false;	
	}
}

function delImages(filed,url){
	if(confirm("確定要刪除嗎?")){
	//alert(id);	
	var filed="#"+filed;
	$.post('<{$baseUrl}>/admin/article/delimage/',{'url':url}, function(data) {
		$(filed).empty();
	});
	}	
	else{
		return false;	
	}
	
}

function ChangeClass(id){
	var fid="#class"+id;
	var inner="#inner"+id;
	var pid=$(fid).val();
	if(pid!='noid'){
	$.post("<{$baseUrl}>/admin/class/ajax/",{id:pid},
  	function(data){
  		//alert(data);
  		var  strArray=new   Array();   
		strArray=data.split("||||");
		$("#sunid").val(strArray[0]);
    	$(inner).empty();
		$(inner).html(strArray[1]); 
  	})
  }else{
  	$(inner).empty();
  	alert('对不起!您没有选择分类!');
  }	
}