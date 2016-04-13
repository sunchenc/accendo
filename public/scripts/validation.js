function mytime(name)
{
    var time=$(name).val();
    alert(time);
    if(time.length==1)
    {
        if(time[0]==0||time[0]==1||time[0]==2)
        {
        }
        else
        {
            time = time.substr(0,0);
            $(name).val(time);
        }
    }
    if(time.length==2)
    {
        if(time[0]==0||time[0]==1)
        {
            if(time[1]==0||time[1]==1||time[1]==2||time[1]==3||time[1]==4||time[1]==5||time[1]==6||time[1]==7||time[1]==8||time[1]==9)
            {
                time=time+':';
                $(name).val(time);
            }
            else
            {
                time = time.substr(0,1);
                $(name).val(time);
            }
        }
        else
        {
            if(time[0]==2)
            {
                if(time[1]==0||time[12]==1||time[12]==2||time[12]==3)
                {
                    time=time+':';
                    $(name).val(time);
                }
                else
                {
                    time = time.substr(0,1);
                    $(name).val(time);
                }
            }
        }
    }
    if(time.length==4)
    {
        if(time[3]==0||time[3]==1||time[3]==2||time[3]==3||time[3]==4||time[3]==5)
        {
        }
        else
        {
            time = time.substr(0,3);
            $(name).val(time);
        }
    }
    if(time.length==5)
    {
        if(time[4]==0||time[4]==1||time[4]==2||time[4]==3||time[4]==4||time[4]==5||time[4]==6||time[4]==7||time[4]==8||time[4]==9)
        {
        }
        else
        {
            time = time.substr(0,4);
            $(name).val(time);
        }
    }
}
function datedate(name) {
    var date = $(name).val();
    if (date.length == 1)
    {
        if (date[0] == 0 || date[0] == 1)
        {
        } else
        {
            $(name).val('');
        }
    }
    if (date.length == 2)
    {
        if (date[0] == 0)
        {
            if (date[1] == 1 || date[1] == 2 || date[1] == 3 || date[1] == 4 || date[1] == 5 || date[1] == 6 || date[1] == 7 || date[1] == 8 || date[1] == 9)
            {
                date = date + '/';
                $(name).val(date);
            } else
                $(name).val(0);
        } else
        {
            if (date[1] == 0 || date[1] == 1 || date[1] == 2)
            {
                date = date + '/';
                $(name).val(date);
            } else
                $(name).val(1);
        }
    }
    if (date.length == 4)
    {
        if ((date[0] == 0 && date[1] == 1) || date[1] == 3 || date[1] == 5 || date[1] == 7 || date[1] == 8 || (date[0] == 1 && date[1] == 0) || (date[0] == 1 && date[1] == 2))
        {
            if (date[3] == 0 || date[3] == 1 || date[3] == 2 || date[3] == 3)
            {
            } else
            {
                date = date.substr(0, 3);
                $(name).val(date);
            }
        } else
        {
            if (date[1] == 4 || date[1] == 6 || date[1] == 9 || (date[0] == 1 && date[1] == 1))
            {
                if (date[3] == 0 || date[3] == 1 || date[3] == 2 || date[3] == 3)
                {
                } else
                {
                    date = date.substr(0, 3);
                    $(name).val(date);
                }
            } else
            {
                if (date[1] == 2)
                {
                    if (date[3] == 0 || date[3] == 1 || date[3] == 2)
                    {
                    } else
                    {
                        date = date.substr(0, 3);
                        $(name).val(date);
                    }
                }
            }
        }
    }
    if (date.length == 5)
    {
        if ((date[0] == 0 && date[1] == 1) || date[1] == 3 || date[1] == 5 || date[1] == 7 || date[1] == 8 || (date[0] == 1 && date[1] == 0) || (date[0] == 1 && date[1] == 2))
        {
            if (date[3] == 0)
            {
                if (date[4] == 1 || date[4] == 2 || date[4] == 3 || date[4] == 4 || date[4] == 5 || date[4] == 6 || date[4] == 7 || date[4] == 8 || date[4] == 9)
                {
                    date = date + '/';
                    $(name).val(date);
                } else
                {
                    date = date.substr(0, 4);
                    $(name).val(date);
                }
            } else
            {
                if (date[3] == 1 || date[3] == 2)
                {
                    if (date[4] == 0 || date[4] == 1 || date[4] == 2 || date[4] == 3 || date[4] == 4 || date[4] == 5 || date[4] == 6 || date[4] == 7 || date[4] == 8 || date[4] == 9)
                    {
                        date = date + '/';
                        $(name).val(date);
                    } else
                    {
                        date = date.substr(0, 4);
                        $(name).val(date);
                    }
                } else
                {
                    if (date[3] == 3)
                    {
                        if (date[4] == 0 || date[4] == 1)
                        {
                            date = date + '/';
                            $(name).val(date);
                        } else
                        {
                            date = date.substr(0, 4);
                            $(name).val(date);
                        }
                    }
                }
            }
        } else
        {
            if (date[1] == 4 || date[1] == 6 || date[1] == 9 || (date[0] == 1 && date[1] == 1))
            {
                if (date[3] == 0)
                {
                    if (date[4] == 1 || date[4] == 2 || date[4] == 3 || date[4] == 4 || date[4] == 5 || date[4] == 6 || date[4] == 7 || date[4] == 8 || date[4] == 9)
                    {
                        date = date + '/';
                        $(name).val(date);
                    } else
                    {
                        date = date.substr(0, 4);
                        $(name).val(date);
                    }
                } else
                {
                    if (date[3] == 1 || date[3] == 2)
                    {
                        if (date[4] == 0 || date[4] == 1 || date[4] == 2 || date[4] == 3 || date[4] == 4 || date[4] == 5 || date[4] == 6 || date[4] == 7 || date[4] == 8 || date[4] == 9)
                        {
                            date = date + '/';
                            $(name).val(date);
                        } else
                        {
                            date = date.substr(0, 4);
                            $(name).val(date);
                        }
                    } else
                    {
                        if (date[3] == 3)
                        {
                            if (date[4] == 0)
                            {
                                date = date + '/';
                                $(name).val(date);
                            } else
                            {
                                date = date.substr(0, 4);
                                $(name).val(date);
                            }
                        }
                    }
                }
            } else
            {
                if (date[1] == 2)
                {
                    if (date[3] == 0)
                    {
                        if (date[4] == 1 || date[4] == 2 || date[4] == 3 || date[4] == 4 || date[4] == 5 || date[4] == 6 || date[4] == 7 || date[4] == 8 || date[4] == 9)
                        {
                            date = date + '/';
                            $(name).val(date);
                        } else
                        {
                            date = date.substr(0, 4);
                            $(name).val(date);
                        }
                    } else
                    {
                        if (date[3] == 1 || date[3] == 2)
                        {
                            if (date[4] == 0 || date[4] == 1 || date[4] == 2 || date[4] == 3 || date[4] == 4 || date[4] == 5 || date[4] == 6 || date[4] == 7 || date[4] == 8 || date[4] == 9)
                            {
                                date = date + '/';
                                $(name).val(date);
                            } else
                            {
                                date = date.substr(0, 4);
                                $(name).val(date);
                            }
                        }
                    }
                }
            }
        }
    }
    if (date.length == 7)
    {
        if (date[6] == 0 || date[6] == 1 || date[6] == 2 || date[6] == 3 || date[6] == 4 || date[6] == 5 || date[6] == 6 || date[6] == 7 || date[6] == 8 || date[6] == 9)
        {
        } else
        {
            date = date.substr(0, 6);
            $(name).val(date);
        }
    }
    if (date.length == 8)
    {
        if (date[7] == 0 || date[7] == 1 || date[7] == 2 || date[7] == 3 || date[7] == 4 || date[7] == 5 || date[7] == 6 || date[7] == 7 || date[7] == 8 || date[7] == 9)
        {
        } else
        {
            date = date.substr(0, 7);
            $(name).val(date);
        }
    }
    if (date.length == 9)
    {
        if (date[8] == 0 || date[8] == 1 || date[8] == 2 || date[8] == 3 || date[8] == 4 || date[8] == 5 || date[8] == 6 || date[8] == 7 || date[8] == 8 || date[8] == 9)
        {
        } else
        {
            date = date.substr(0, 8);
            $(name).val(date);
        }
    }
    if (date.length == 10)
    {
        if (date[9] == 0 || date[9] == 1 || date[9] == 2 || date[9] == 3 || date[9] == 4 || date[9] == 5 || date[9] == 6 || date[9] == 7 || date[9] == 8 || date[9] == 9)
        {
            var year = parseInt(date.substr(6, 10));

            if (date[0] == 0 && date[1] == 2 && date[3] == 2 && date[4] == 9)
            {
                if (year % 400 == 0)
                {
                } else
                {
                    if ((year % 4 == 0) && (year % 100 != 0))
                    {
                    } else
                    {
                        date = date.substr(0, 9);
                        $(name).val(date);
                    }
                }
            }
        } else
        {
            date = date.substr(0, 9);
            $(name).val(date);
        }
    } 
     if (date.length == 11){
           date = date.substr(0, 10);
           date = date + '-';
           $(name).val(date);
        }
    
            if (date.length == 12)
            {
                if (date[11] == 0 || date[11] == 1)
                {
                } else
                {
                     date = date.substr(0, 11);
                     $(name).val(date);
                }
            }
            if (date.length == 13)
            {
                if (date[11] == 0)
                {
                    if (date[12] == 1 || date[12] == 2 || date[12] == 3 || date[12] == 4 || date[12] == 5 || date[12] == 6 || date[12] == 7 || date[12] == 8 || date[12] == 9)
                    {
                        date = date + '/';
                        $(name).val(date);
                    } else
                    { date = date.substr(0, 12);
                     $(name).val(date);
                 }
                } else
                {
                    if (date[12] == 0 || date[12] == 1 || date[12] == 2)
                    {
                        date = date + '/';
                        $(name).val(date);
                    } else
                    {   date = date.substr(0, 12);
                     $(name).val(date);
                 }
                }
            }
            if (date.length == 15)
            {
                if ((date[11] == 0 && date[12] == 1) || date[12] == 3 || date[12] == 5 || date[12] == 7 || date[12] == 8 || (date[11] == 1 && date[12] == 0) || (date[11] == 1 && date[12] == 2))
                {
                    if (date[14] == 0 || date[14] == 1 || date[14] == 2 || date[14] == 3)
                    {
                    } else
                    {
                        date = date.substr(0, 14);
                        $(name).val(date);
                    }
                } else
                {
                    if (date[12] == 4 || date[12] == 6 || date[12] == 9 || (date[11] == 1 && date[12] == 1))
                    {
                        if (date[14] == 0 || date[14] == 1 || date[14] == 2 || date[14] == 3)
                        {
                        } else
                        {
                            date = date.substr(0, 14);
                            $(name).val(date);
                        }
                    } else
                    {
                        if (date[12] == 2)
                        {
                            if (date[14] == 0 || date[14] == 1 || date[14] == 2)
                            {
                            } else
                            {
                                date = date.substr(0, 14);
                                $(name).val(date);
                            }
                        }
                    }
                }
            }
            if (date.length ==16)
            {
                if ((date[11] == 0 && date[12] == 1) || date[12] == 3 || date[12] == 5 || date[12] == 7 || date[12] == 8 || (date[11] == 1 && date[12] == 0) || (date[11] == 1 && date[12] == 2))
                {
                    if (date[14] == 0)
                    {
                        if (date[15] == 1 || date[15] == 2 || date[15] == 3 || date[15] == 4 || date[15] == 5 || date[15] == 6 || date[15] == 7 || date[15] == 8 || date[15] == 9)
                        {
                            date = date + '/';
                            $(name).val(date);
                        } else
                        {
                            date = date.substr(0, 15);
                            $(name).val(date);
                        }
                    } else
                    {
                        if (date[14] == 1 || date[14] == 2)
                        {
                            if (date[15] == 0 || date[15] == 1 || date[15] == 2 || date[15] == 3 || date[15] == 4 || date[15] == 5 || date[15] == 6 || date[15] == 7 || date[15] == 8 || date[15] == 9)
                            {
                                date = date + '/';
                                $(name).val(date);
                            } else
                            {
                                date = date.substr(0, 15);
                                $(name).val(date);
                            }
                        } else
                        {
                            if (date[14] == 3)
                            {
                                if (date[15] == 0 || date[15] == 1)
                                {
                                    date = date + '/';
                                    $(name).val(date);
                                } else
                                {
                                    date = date.substr(0, 15);
                                    $(name).val(date);
                                }
                            }
                        }
                    }
                } else
                {
                    if (date[12] == 4 || date[12] == 6 || date[12] == 9 || (date[11] == 1 && date[12] == 1))
                    {
                        if (date[14] == 0)
                        {
                            if (date[15] == 1 || date[15] == 2 || date[15] == 3 || date[15] == 4 || date[15] == 5 || date[15] == 6 || date[15] == 7 || date[15] == 8 || date[15] == 9)
                            {
                                date = date + '/';
                                $(name).val(date);
                            } else
                            {
                                date = date.substr(0, 15);
                                $(name).val(date);
                            }
                        } else
                        {
                            if (date[14] == 1 || date[14] == 2)
                            {
                                if (date[15] == 0 || date[15] == 1 || date[15] == 2 || date[15] == 3 || date[15] == 4 || date[15] == 5 || date[15] == 6 || date[15] == 7 || date[15] == 8 || date[15] == 9)
                                {
                                    date = date + '/';
                                    $(name).val(date);
                                } else
                                {
                                    date = date.substr(0, 15);
                                    $(name).val(date);
                                }
                            } else
                            {
                                if (date[14] == 3)
                                {
                                    if (date[15] == 0)
                                    {
                                        date = date + '/';
                                        $(name).val(date);
                                    } else
                                    {
                                        date = date.substr(0, 15);
                                        $(name).val(date);
                                    }
                                }
                            }
                        }
                    } else
                    {
                        if (date[12] == 2)
                        {
                            if (date[14] == 0)
                            {
                                if (date[15] == 1 || date[15] == 2 || date[15] == 3 || date[15] == 4 || date[15] == 5 || date[15] == 6 || date[15] == 7 || date[15] == 8 || date[15] == 9)
                                {
                                    date = date + '/';
                                    $(name).val(date);
                                } else
                                {
                                    date = date.substr(0, 15);
                                    $(name).val(date);
                                }
                            } else
                            {
                                if (date[14] == 1 || date[14] == 2)
                                {
                                    if (date[15] == 0 || date[15] == 1 || date[15] == 2 || date[15] == 3 || date[15] == 4 || date[15] == 5 || date[15] == 6 || date[15] == 7 || date[15] == 8 || date[15] == 9)
                                    {
                                        date = date + '/';
                                        $(name).val(date);
                                    } else
                                    {
                                        date = date.substr(0, 15);
                                        $(name).val(date);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (date.length == 18)
            {
                if (date[17] == 0 || date[17] == 1 || date[17] == 2 || date[17] == 3 || date[17] == 4 || date[17] == 5 || date[17] == 6 || date[17] == 7 || date[17] == 8 || date[17] == 9)
                {
                } else
                {
                    date = date.substr(0, 17);
                    $(name).val(date);
                }
            }
            if (date.length == 19)
            {
                if (date[18] == 0 || date[18] == 1 || date[18] == 2 || date[18] == 3 || date[18] == 4 || date[18] == 5 || date[18] == 6 || date[18] == 7 || date[18] == 8 || date[18] == 9)
                {
                } else
                {
                    date = date.substr(0, 18);
                    $(name).val(date);
                }
            }
            if (date.length == 20)
            {
                if (date[19] == 0 || date[19] == 1 || date[19] == 2 || date[19] == 3 || date[19] == 4 || date[19] == 5 || date[19] == 6 || date[19] == 7 || date[19] == 8 || date[19] == 9)
                {
                } else
                {
                    date = date.substr(0,19);
                    $(name).val(date);
                }
            }
            if (date.length == 21)
            {
                if (date[20] == 0 || date[20] == 1 || date[20] == 2 || date[20] == 3 || date[20] == 4 || date[20] == 5 || date[20] == 6 || date[20] == 7 || date[20] == 8 || date[20] == 9)
                {
                    var year = parseInt(date.substr(0, 21));

                    if (date[11] == 0 && date[12] == 2 && date[14] == 2 && date[15] == 9)
                    {
                        if (year % 400 == 0)
                        {
                        } else
                        {
                            if ((year % 4 == 0) && (year % 100 != 0))
                            {
                            } else
                            {
                                date = date.substr(0, 20);
                                $(name).val(date);
                            }
                        }
                    }
                } else
                {
                    date = date.substr(0, 20);
                    $(name).val(date);
                }
            }
            if (date.length > 21)
            {
                date = date.substr(0, 21);
                $(name).val(date);
            }
}
function date(name)
{
    var date=$(name).val();

    if(date.length==1)
    {
        if(date[0]==0||date[0]==1)
        {
        }
        else
        {
            $(name).val('');
        }
    }
    if(date.length==2)
    {
        if(date[0]==0)
        {
            if(date[1]==1||date[1]==2||date[1]==3||date[1]==4||date[1]==5||date[1]==6||date[1]==7||date[1]==8||date[1]==9)
            {
                date=date+'/';
                $(name).val(date);
            }
            else
                $(name).val(0);
        }
        else
        {
            if(date[1]==0||date[1]==1||date[1]==2)
            {
                date=date+'/';
                $(name).val(date);
            }
            else
                $(name).val(1);
        }
    }
    if(date.length==4)
    {
        if((date[0]==0&&date[1]==1)||date[1]==3||date[1]==5||date[1]==7||date[1]==8||(date[0]==1&&date[1]==0)||(date[0]==1&&date[1]==2))
        {
            if(date[3]==0||date[3]==1||date[3]==2||date[3]==3)
            {
            }
            else
            {
                date = date.substr(0,3);
                $(name).val(date);
            }
        }
        else
        {
            if(date[1]==4||date[1]==6||date[1]==9||(date[0]==1&&date[1]==1))
            {
                if(date[3]==0||date[3]==1||date[3]==2||date[3]==3)
                {
                }
                else
                {
                    date = date.substr(0,3);
                    $(name).val(date);
                }
            }
            else
            {
                if(date[1]==2)
                {
                    if(date[3]==0||date[3]==1||date[3]==2)
                    {
                    }
                    else
                    {
                        date = date.substr(0,3);
                        $(name).val(date);
                    }
                }
            }
        }
    }
    if(date.length==5)
    {
        if((date[0]==0&&date[1]==1)||date[1]==3||date[1]==5||date[1]==7||date[1]==8||(date[0]==1&&date[1]==0)||(date[0]==1&&date[1]==2))
        {
            if(date[3]==0)
            {
                if(date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                {
                    date=date+'/';
                    $(name).val(date);
                }
                else
                {
                    date = date.substr(0,4);
                    $(name).val(date);
                }
            }
            else
            {
                if(date[3]==1||date[3]==2)
                {
                    if(date[4]==0||date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                    {
                        date=date+'/';
                        $(name).val(date);
                    }
                    else
                    {
                        date = date.substr(0,4);
                        $(name).val(date);
                    }
                }
                else
                {
                    if(date[3]==3)
                    {
                        if(date[4]==0||date[4]==1)
                        {
                            date=date+'/';
                            $(name).val(date);
                        }
                        else
                        {
                            date = date.substr(0,4);
                            $(name).val(date);
                        }
                    }
                }
            }
        }
        else
        {
            if(date[1]==4||date[1]==6||date[1]==9||(date[0]==1&&date[1]==1))
            {
                if(date[3]==0)
                {
                    if(date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                    {
                        date=date+'/';
                        $(name).val(date);
                    }
                    else
                    {
                        date = date.substr(0,4);
                        $(name).val(date);
                    }
                }
                else
                {
                    if(date[3]==1||date[3]==2)
                    {
                        if(date[4]==0||date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                        {
                            date=date+'/';
                            $(name).val(date);
                        }
                        else
                        {
                            date = date.substr(0,4);
                            $(name).val(date);
                        }
                    }
                    else
                    {
                        if(date[3]==3)
                        {
                            if(date[4]==0)
                            {
                                date=date+'/';
                                $(name).val(date);
                            }
                            else
                            {
                                date = date.substr(0,4);
                                $(name).val(date);
                            }
                        }
                    }
                }
            }
            else
            {
                if(date[1]==2)
                {
                    if(date[3]==0)
                    {
                        if(date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                        {
                            date=date+'/';
                            $(name).val(date);
                        }
                        else
                        {
                            date = date.substr(0,4);
                            $(name).val(date);
                        }
                    }
                    else
                    {
                        if(date[3]==1||date[3]==2)
                        {
                            if(date[4]==0||date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                            {
                                date=date+'/';
                                $(name).val(date);
                            }
                            else
                            {
                                date = date.substr(0,4);
                                $(name).val(date);
                            }
                        }
                    }
                }
            }
        }
    }
    if(date.length==7)
    {
        if(date[6]==0||date[6]==1||date[6]==2||date[6]==3||date[6]==4||date[6]==5||date[6]==6||date[6]==7||date[6]==8||date[6]==9)
        {
        }
        else
        {
            date = date.substr(0,6);
            $(name).val(date);
        }
    }
    if(date.length==8)
    {
        if(date[7]==0||date[7]==1||date[7]==2||date[7]==3||date[7]==4||date[7]==5||date[7]==6||date[7]==7||date[7]==8||date[7]==9)
        {
        }
        else
        {
            date = date.substr(0,7);
            $(name).val(date);
        }
    }
    if(date.length==9)
    {
        if(date[8]==0||date[8]==1||date[8]==2||date[8]==3||date[8]==4||date[8]==5||date[8]==6||date[8]==7||date[8]==8||date[8]==9)
        {
        }
        else
        {
            date = date.substr(0,8);
            $(name).val(date);
        }
    }
    if(date.length==10)
    {
        if(date[9]==0||date[9]==1||date[9]==2||date[9]==3||date[9]==4||date[9]==5||date[9]==6||date[9]==7||date[9]==8||date[9]==9)
        {
            var year=parseInt(date.substr(6,10));

            if(date[0]==0&&date[1]==2&&date[3]==2&&date[4]==9)
            {
                if(year%400==0)
                {
                }
                else
                {
                    if((year%4==0)&&(year%100!=0))
                    {
                    }
                    else
                    {
                        date = date.substr(0,9);
                        $(name).val(date);
                    }
                }
            }
        }
        else
        {
            date = date.substr(0,9);
            $(name).val(date);
        }
    }
    if(date.length>10)
    {
        date = date.substr(0,10);
        $(name).val(date);
    }
}

function dateandtime(name)
{
    var date=$(name).val();

    if(date.length==1)
    {
        if(date[0]==0||date[0]==1)
        {
        }
        else
        {
            $(name).val('');
        }
    }
    if(date.length==2)
    {
        if(date[0]==0)
        {
            if(date[1]==1||date[1]==2||date[1]==3||date[1]==4||date[1]==5||date[1]==6||date[1]==7||date[1]==8||date[1]==9)
            {
                date=date+'/';
                $(name).val(date);
            }
            else
                $(name).val(0);
        }
        else
        {
            if(date[1]==0||date[1]==1||date[1]==2)
            {
                date=date+'/';
                $(name).val(date);
            }
            else
                $(name).val(1);
        }
    }
    if(date.length==4)
    {
        if((date[0]==0&&date[1]==1)||date[1]==3||date[1]==5||date[1]==7||date[1]==8||(date[0]==1&&date[1]==0)||(date[0]==1&&date[1]==2))
        {
            if(date[3]==0||date[3]==1||date[3]==2||date[3]==3)
            {
            }
            else
            {
                date = date.substr(0,3);
                $(name).val(date);
            }
        }
        else
        {
            if(date[1]==4||date[1]==6||date[1]==9||(date[0]==1&&date[1]==1))
            {
                if(date[3]==0||date[3]==1||date[3]==2||date[3]==3)
                {
                }
                else
                {
                    date = date.substr(0,3);
                    $(name).val(date);
                }
            }
            else
            {
                if(date[1]==2)
                {
                    if(date[3]==0||date[3]==1||date[3]==2)
                    {
                    }
                    else
                    {
                        date = date.substr(0,3);
                        $(name).val(date);
                    }
                }
            }
        }
    }
    if(date.length==5)
    {
        if((date[0]==0&&date[1]==1)||date[1]==3||date[1]==5||date[1]==7||date[1]==8||(date[0]==1&&date[1]==0)||(date[0]==1&&date[1]==2))
        {
            if(date[3]==0)
            {
                if(date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                {
                    date=date+'/';
                    $(name).val(date);
                }
                else
                {
                    date = date.substr(0,4);
                    $(name).val(date);
                }
            }
            else
            {
                if(date[3]==1||date[3]==2)
                {
                    if(date[4]==0||date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                    {
                        date=date+'/';
                        $(name).val(date);
                    }
                    else
                    {
                        date = date.substr(0,4);
                        $(name).val(date);
                    }
                }
                else
                {
                    if(date[3]==3)
                    {
                        if(date[4]==0||date[4]==1)
                        {
                            date=date+'/';
                            $(name).val(date);
                        }
                        else
                        {
                            date = date.substr(0,4);
                            $(name).val(date);
                        }
                    }
                }
            }
        }
        else
        {
            if(date[1]==4||date[1]==6||date[1]==9||(date[0]==1&&date[1]==1))
            {
                if(date[3]==0)
                {
                    if(date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                    {
                        date=date+'/';
                        $(name).val(date);
                    }
                    else
                    {
                        date = date.substr(0,4);
                        $(name).val(date);
                    }
                }
                else
                {
                    if(date[3]==1||date[3]==2)
                    {
                        if(date[4]==0||date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                        {
                            date=date+'/';
                            $(name).val(date);
                        }
                        else
                        {
                            date = date.substr(0,4);
                            $(name).val(date);
                        }
                    }
                    else
                    {
                        if(date[3]==3)
                        {
                            if(date[4]==0)
                            {
                                date=date+'/';
                                $(name).val(date);
                            }
                            else
                            {
                                date = date.substr(0,4);
                                $(name).val(date);
                            }
                        }
                    }
                }
            }
            else
            {
                if(date[1]==2)
                {
                    if(date[3]==0)
                    {
                        if(date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                        {
                            date=date+'/';
                            $(name).val(date);
                        }
                        else
                        {
                            date = date.substr(0,4);
                            $(name).val(date);
                        }
                    }
                    else
                    {
                        if(date[3]==1||date[3]==2)
                        {
                            if(date[4]==0||date[4]==1||date[4]==2||date[4]==3||date[4]==4||date[4]==5||date[4]==6||date[4]==7||date[4]==8||date[4]==9)
                            {
                                date=date+'/';
                                $(name).val(date);
                            }
                            else
                            {
                                date = date.substr(0,4);
                                $(name).val(date);
                            }
                        }
                    }
                }
            }
        }
    }
    if(date.length==7)
    {
        if(date[6]==0||date[6]==1||date[6]==2||date[6]==3||date[6]==4||date[6]==5||date[6]==6||date[6]==7||date[6]==8||date[6]==9)
        {
        }
        else
        {
            date = date.substr(0,6);
            $(name).val(date);
        }
    }
    if(date.length==8)
    {
        if(date[7]==0||date[7]==1||date[7]==2||date[7]==3||date[7]==4||date[7]==5||date[7]==6||date[7]==7||date[7]==8||date[7]==9)
        {
        }
        else
        {
            date = date.substr(0,7);
            $(name).val(date);
        }
    }
    if(date.length==9)
    {
        if(date[8]==0||date[8]==1||date[8]==2||date[8]==3||date[8]==4||date[8]==5||date[8]==6||date[8]==7||date[8]==8||date[8]==9)
        {
        }
        else
        {
            date = date.substr(0,8);
            $(name).val(date);
        }
    }
    if(date.length==10)
    {
        if(date[9]==0||date[9]==1||date[9]==2||date[9]==3||date[9]==4||date[9]==5||date[9]==6||date[9]==7||date[9]==8||date[9]==9)
        {
            var year=parseInt(date.substr(6,10));
            if(date[0]==0&&date[1]==2&&date[3]==2&&date[4]==9)
            {
                if(year%400==0)
                {
                    date=date+' ';
                    $(name).val(date);
                }
                else
                {
                    if((year%4==0)&&(year%100!=0))
                    {
                        date=date+' ';
                        $(name).val(date);
                    }
                    else
                    {
                        date = date.substr(0,9);
                        $(name).val(date);
                    }
                }
            }
        }
        else
        {
            date = date.substr(0,9);
            $(name).val(date);
        }
    }
    if(date.length==12)
    {
        if(date[11]==0||date[11]==1||date[11]==2)
        {
        }
        else
        {
            date = date.substr(0,11);
            $(name).val(date);
        }
    }
    if(date.length==13)
    {
        if(date[11]==0||date[11]==1)
        {
            if(date[12]==0||date[12]==1||date[12]==2||date[12]==3||date[12]==4||date[12]==5||date[12]==6||date[12]==7||date[12]==8||date[12]==9)
            {
                date=date+':';
                $(name).val(date);
            }
            else
            {
                date = date.substr(0,12);
                $(name).val(date);
            }
        }
        else
        {
            if(date[11]==2)
            {
                if(date[12]==0||date[12]==1||date[12]==2||date[12]==3)
                {
                    date=date+':';
                    $(name).val(date);
                }
                else
                {
                    date = date.substr(0,12);
                    $(name).val(date);
                }
            }
        }
    }
    if(date.length==15)
    {
        if(date[14]==0||date[14]==1||date[14]==2||date[14]==3||date[14]==4||date[14]==5)
        {
        }
        else
        {
            date = date.substr(0,14);
            $(name).val(date);
        }
    }
    if(date.length==16)
    {
        if(date[15]==0||date[15]==1||date[15]==2||date[15]==3||date[15]==4||date[15]==5||date[15]==6||date[15]==7||date[15]==8||date[15]==9)
        {
        }
        else
        {
            date = date.substr(0,15);
            $(name).val(date);
        }
    }
    if(date.length>16)
    {
        date = date.substr(0,16);
        $(name).val(date);
    }
};
//add by Pan to show the rightTime 
function showtime(){
    today=new Date();
    var year=today.getYear();
    var month=today.getMonth()+1;
    var day=today.getDate();
    var hours = today.getHours();
    var minutes = today.getMinutes();
    var seconds = today.getSeconds();

    var timeValue1 = ((hours < 10) ? "0" : "") + hours+"";
    timeValue1 += ((minutes < 10) ? ":0" : ":") + minutes+"";
//    timeValue1 += ((seconds < 10) ? ":0" : ":") + seconds+"";

    var timeValue2 = year;
    timeValue2 += ((month < 10) ? "/0" : "/") + month+"";
    timeValue2 += ((day < 10) ? "/0" : "/") + day+"";

    var timetext=timeValue2+" "+timeValue1
    return timetext;
}