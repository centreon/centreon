<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
<xsl:template match="root">
<div>
    <div id="div2" valign="top" align='left'></div>
</div>
<div>
    <div>
        <table class="ToolbarTable table eventLogsPager">
            <tr class="ToolbarTR">
                <td></td>
                <td class="ToolbarPagination"><xsl:call-template name="pageButtons"/></td>
                <td class="Toolbar_pagelimit">
                            <xsl:element name='a'>
                                <xsl:attribute name="href">javascript:logs(this, '', 'CSV')</xsl:attribute>
                                <xsl:element name='img'>
                                    <xsl:attribute name="title">Export CSV</xsl:attribute>
                                    <xsl:attribute name="alt">Export CSV</xsl:attribute>
                                    <xsl:attribute name="class">ico-20</xsl:attribute>
                                    <xsl:attribute name="src">./img/icons/csv.png</xsl:attribute>
                                </xsl:element>
                            </xsl:element>
                            <xsl:element name='a'>
                                <xsl:attribute name="href">javascript:logs(this, '', 'XML')</xsl:attribute>
                                <xsl:element name='img'>
                                    <xsl:attribute name="title">Export XML</xsl:attribute>
                                    <xsl:attribute name="alt">Export XML</xsl:attribute>
                                    <xsl:attribute name="class">ico-20</xsl:attribute>
                                    <xsl:attribute name="src">./img/icons/xml.png</xsl:attribute>
                                </xsl:element>
                            </xsl:element>
                            <xsl:element name='select'>
                                <xsl:attribute name="onChange">setL(this.value); log_4_host_page('<xsl:value-of select="//opid"/>', this.form, 1); return false;</xsl:attribute>
                                <xsl:attribute name="name">l</xsl:attribute>
                                    <xsl:for-each select="//limitValue">
                                        <xsl:element name='option'>
                                            <xsl:attribute name="value"><xsl:value-of select="current()"/></xsl:attribute>
                                            <xsl:if test="current() = //limit">
                                                <xsl:attribute name="selected">selected</xsl:attribute>
                                            </xsl:if>
                                            <xsl:value-of select="current()"/>
                                        </xsl:element>
                                    </xsl:for-each>
                            </xsl:element>
                        </td>
                    </tr>
        </table>
    </div>
    <div style="">
    <table class="ListTable">
        <tr class='ListHeader'>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/d"/></td>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/t"/></td>
            <td class="ListColHeaderLeft" colspan="2"><xsl:value-of select="//lang/O"/></td>
            <td class="ListColHeaderCenter" style='width:50px;'><xsl:value-of select="//lang/s"/></td>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/T"/></td>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/R"/></td>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/o"/></td>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/c"/></td>
            <td class="ListColHeaderCenter"><xsl:value-of select="//lang/C"/></td>
        </tr>
        <xsl:for-each select="//line">
        <tr>
            <xsl:attribute name="class"><xsl:value-of select="class"/></xsl:attribute>
            <td style="padding-left:5px;" class="ListColCenter isTimestamp isDate"><xsl:value-of select="date"/></td>
            <td style="padding-left:5px;" class="ListColCenter isTimestamp isTime"><xsl:value-of select="time"/></td>
            <td style="padding-left:5px;">
                <xsl:if test="host_name != 'Meta'">
                    <xsl:element name="a">
                        <xsl:attribute name="href"><xsl:value-of select="h_timeline_uri" /></xsl:attribute>
                        <xsl:attribute name="target">_blank</xsl:attribute>
                        <xsl:value-of select="host_name"/>
                    </xsl:element>
                </xsl:if>
                <xsl:if test="host_name = 'Meta'">
                    <xsl:value-of select="host_name"/>
                </xsl:if>
            </td>
            <td style="padding-left:5px;">
                <xsl:element name="a">
                    <xsl:attribute name="href"><xsl:value-of select="s_timeline_uri" /></xsl:attribute>
                    <xsl:attribute name="target">_blank</xsl:attribute>
                    <xsl:value-of select="service_description"/>
                </xsl:element>
            </td>
            <td style="padding-left:5px;" class="ListColCenter">
              <xsl:element name="span">
                <xsl:attribute name="class">badge <xsl:value-of select="status/@color"/></xsl:attribute>
                <xsl:value-of select="status"/>
              </xsl:element>
            </td>
            <td style="padding-left:5px;padding-right:5px;" class="ListColCenter"><xsl:value-of select="type"/></td>
            <td style="padding-left:5px;padding-right:5px;" class="ListColCenter"><xsl:value-of select="retry"/></td>
            <td style="padding-left:5px;"><xsl:value-of select="output"/></td>
            <td style="padding-left:5px;"><xsl:value-of select="contact"/></td>
            <td style="padding-left:5px;"><xsl:value-of select="contact_cmd"/></td>
        </tr>
        </xsl:for-each>
    </table>
    </div>
    <div class="eventLogsPagerBottom">
        <table class="ToolbarTable table eventLogsPager">
            <tr class="ToolbarTR">
                <td></td>
                <td class="ToolbarPagination"><xsl:call-template name="pageButtons"/></td>
                <td class="Toolbar_pagelimit">   
                    <xsl:element name='select'>
                        <xsl:attribute name="onChange">setL(this.value); log_4_host_page('<xsl:value-of select="//opid"/>', this.form, 1); return false;</xsl:attribute>
                        <xsl:attribute name="name">l</xsl:attribute>
                            <xsl:for-each select="//limitValue">
                                <xsl:element name='option'>
                                    <xsl:attribute name="value"><xsl:value-of select="current()"/></xsl:attribute>
                                    <xsl:if test="current() = //limit">
                                        <xsl:attribute name="selected">selected</xsl:attribute>
                                    </xsl:if>
                                    <xsl:value-of select="current()"/>
                                </xsl:element>
                            </xsl:for-each>
                    </xsl:element>
                </td>
            </tr>
        </table>
    </div>
</div>
</xsl:template>
<!-- Shown above and below the list, disabled on the first page and when no more events match. -->
<xsl:template name="pageButtons">
    <xsl:element name='input'>
        <xsl:attribute name="type">button</xsl:attribute>
        <xsl:attribute name="name">previousPage</xsl:attribute>
        <xsl:attribute name="class">btc bt_info</xsl:attribute>
        <xsl:attribute name="value"><xsl:value-of select="//lang/previousPage"/></xsl:attribute>
        <xsl:choose>
            <xsl:when test="/root/previousPageNumber">
                <xsl:attribute name="onClick">log_4_host_page('<xsl:value-of select="//opid"/>', this.form, '<xsl:value-of select="/root/previousPageNumber"/>'); return false;</xsl:attribute>
            </xsl:when>
            <xsl:otherwise>
                <xsl:attribute name="disabled">disabled</xsl:attribute>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:element>
    <xsl:element name='input'>
        <xsl:attribute name="type">button</xsl:attribute>
        <xsl:attribute name="name">nextPage</xsl:attribute>
        <xsl:attribute name="class">btc bt_info</xsl:attribute>
        <xsl:attribute name="value"><xsl:value-of select="//lang/nextPage"/></xsl:attribute>
        <xsl:choose>
            <xsl:when test="/root/nextPageNumber">
                <xsl:attribute name="onClick">log_4_host_page('<xsl:value-of select="//opid"/>', this.form, '<xsl:value-of select="/root/nextPageNumber"/>'); return false;</xsl:attribute>
            </xsl:when>
            <xsl:otherwise>
                <xsl:attribute name="disabled">disabled</xsl:attribute>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:element>
</xsl:template>
</xsl:stylesheet>
