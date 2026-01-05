<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes"/>

    <xsl:template match="/">
        <div class="meteo-grid">
            <xsl:for-each select="weather/period">
                <div class="meteo-slot">
                    <p class="meta">
                        <xsl:value-of select="name"/>
                        <xsl:text> • </xsl:text>
                        <xsl:value-of select="time"/>
                    </p>
                    <p class="icon">
                        <xsl:value-of select="icon"/>
                    </p>
                    <p class="condition">
                        <xsl:value-of select="condition"/>
                    </p>
                    <p class="temp">
                        <xsl:value-of select="temp"/>
                        <xsl:text>°C</xsl:text>
                    </p>
                    <p class="meta">
                        <xsl:text>Vent </xsl:text>
                        <xsl:value-of select="wind"/>
                        <xsl:text> km/h • Pluie </xsl:text>
                        <xsl:value-of select="precip"/>
                        <xsl:text> mm</xsl:text>
                    </p>
                </div>
            </xsl:for-each>
        </div>
    </xsl:template>
</xsl:stylesheet>
