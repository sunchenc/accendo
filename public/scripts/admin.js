function	ChangImg()
{	var checkcode = $("#yanzheng").get(0);
	var dt = new Date();
	checkcode.src = "/admin/index/imgcode/t=" + dt;	
}
