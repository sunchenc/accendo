
function mk_buttonadd_str(filename,root,button_value)
{
    var button_str='';
    var space='&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    if(filename==null||(filename==''))
    {
        button_str = '<input type="button"  class="" value="'+button_value +'"disabled="disabled")"/>'+space;
        return button_str;
    }
    var filepath=root+'/'+filename;
    var value=filename.substring(filename.lastIndexOf('/')+1,filename.lastIndexOf('.'));
    if(button_value!='')
        value=button_value;
    button_str = '<input type="button" value="'+value +'"onclick="javascript:window.open(\''+filepath+'\')"/>'+space;
    return button_str;
}