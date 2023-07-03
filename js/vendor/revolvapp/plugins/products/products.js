/*
    Webasyst LLC
    Version 1.0.0

    https://www.webasyst.com/

    Copyright (c) 2022, Webasyst
*/
(function () {
    // https://imperavi.com/revolvapp/docs/syntax/tags/

    let icons = {
        product_top  : '<img alt="product top" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAgCAYAAABgrToAAAAACXBIWXMAAA57AAAOewEd6h3MAAACLUlEQVRYhe1YPUgcQRT+3qgExCaFCBoVNNgIBlJY2OgZMCFglYCClTEEbFIEREgTS0ErFcSElIK9EeFQ16RIEy1tRCUhEgTThITE4m4m343iD2gx8w5Z4d5xu7Mz99733bczb96uIDEOKTaDlFv5aStjBWmykzt7gxTU2Cru81iDB1hGgio4MwKxv5HBpDZ0cRQU0+/Pa3gBh1ckN82zwSIqtaH1BAskBHmvnhQ+pprK/eS5Ab34qw2vJ1ggkbdZTupxqnYLcHfZnkDOvkcRLG4OJmYW4joudnLRiZR7kuLqUCE9WD8/LJ+ZKYZDoeIIimsiYNuV4+5Sn4MYqNSnmRJBrZUIai1uFecc04WrCvT6EwMVmWa4bxjTEuRj7TaPe6FQcQTL5ClzXU+gT5aqvwuFiiNoZZOJV4J9EF68xxHstq/DneKeLGL34gUCdoU5yTr34v5QqDiCEUDXq+B5SzDECvoeJ9kOujHF60LMUabYO3D2A/uWNOF1iXoFbSTXzDn50hetCR5RqGcc2TourcwTZFGrgdARLEMDy/td37b4wio6w289r3aOf2A3UIF2DYT2Fq/wP87wWaSRCt6mer9IipW0eUM199nXwgQ9rwHQEczgiISe+/YqhknoG+fcV/YN+r418xY5bGggirFIBqgcFTRN+Gjn/ENUJfq4FbaS6C73mx+a8HqCec69PD7hof3ur8eoaif7/nEFP8ahNvzZy6OUvvoovd3SGfAfyPWbPp/fCBAAAAAASUVORK5CYII=">',
        product_left : '<img alt="product left" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAgCAYAAABgrToAAAAACXBIWXMAAA57AAAOewEd6h3MAAAC2UlEQVRYhe1Yz0tUURT+7p2Hps1ClMpNESQRFPQHJDE6UZskYkKQggj6QRBB4aY0VISoRZvCRe4KFyG0MgzJ5hURQRkUtCoJhUlC+21Y1sy9fe8p+pwfvHnqHafoG96dO+e9c/juOfedc+4I2FKjiCFR5LDmZnVKoJgwG9mi96A/QRvVaF+5hVi+TwgxgAhG0Keb0IApV2ajgmtrA/R2XmdRhxcZeoPYzHFVIDYhvKWt716RlYdaAil9GWF5FVBHSS5M1r2cn+e9LhI9wXkmwZC4zee2IQiUinKMe0X+oVNiFBpfIPCR5GqhZTcJt3ClQ7xbAa2+wSD8PfhbtaNE3CSRQ264hU6gHs/Qh3J6qI2/T8Ig/AnuwTji+hqEvERPPqFLhzGA9STdzf3XQU8msuql9HHeDwdik8zcy/OVxC8P3kcTpOjk7A2vH1D6DKIYhSnM8spOMI599FhthpJAJb0Y4mwiu1V1hR59704HUYX8XsJ5fMJnNOKXl2B2A1LWk8jpDLl/1b7Ba4ag5WwB1CAIqvQxjk+9omArDIKIji2HGXMEHzh5MKAHU/RgtFAeTOpzWEwlSYM5giEWRsjqYErqOodhr8QcQYXXHMYD6ciFddiBOYJR1pplwF/UUZtCHDEm/QNMomV8S7uwC/f42QBLXmTiF8ytbLHUhVzqZj3oNBRCnMJDdRATupHJuxW9KEGIdR2qFRHKoabZJe3NZcIswVKnscUHduTKLWFajLFY1kDqdcBck8G2TUZymcge4qTqofOHAhOSaZ3NbozBFtOIi5lwQm9x5VqzDMoe2OoVv7dS8i6Xyfy7maWgH2uYQL5irbiLSd3gHh3c8NPH5djP7yk2GbcW6BTsVNdPEmWYJMUj7M5fuuScffgcP7Eam9jCHeZTd3Kpm3+LS7n7IHfwvPEYj1SzK6vERuyUzezSRxjvWPpByYvChHgx+HcO7iuM//9uLQ3AH1sb3V/Db1oYAAAAAElFTkSuQmCC">',
        product_right: '<img alt="product right" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAgCAYAAABgrToAAAAACXBIWXMAAA57AAAOewEd6h3MAAACz0lEQVRYhe1YPWgUQRh9MxcSxSjKBaKgYiqbEH+KmEKIlxyiYCoRFX9jKwhGC4moCWIhQUQFG0EQO7GKIBIvt5HYiYUiYqFYGhWixkUPOebz7V5cTfZHl+zCCnnHze58OzP35n0z33x7CpYWZBgaGUedd1cwClnCtGczr2D6BB9gxVy61wVaS2iLPVIOL1FAdYZtFBuRUzdQVo/QZfo8exlrqc05KFkGW3aiB9/ChvUTtBybfoa4+GGaWX6YYRNUIMqCMhMc9xAncIvXVih1EVVzhJPagUUosuVw2LDpurhIVSHrYOM6oPaQ3BpeL0NkH5+95wSW8GtHDZH+GjQyhMX6PIleILm7rA9Tyc94iNVUsoBxjEV197vYWUclszU2kS/80SB0Y4QUOmHQQpcupCTjVHIzyZ4m6V4M8EkEgjdJkfNLEmPmDDr1JRJqotNO0fKO0u6iGPbfuv4+6v4M1CU0xybxGB89NSycIJHlvjaCVfzFT7wLIGZsEh70qtO8gnexUiOIiw4psJysVdRhFq2+NlFnlVKMADI42xy8Bp2dlxGEKfg09kgVKrjtl4LJIWQXy4HYIzVgCinAT3CAgSCn4xOEcdaPjYQRHGaMeR57JD3rHE4IQQo6oeI2MoL/KKMOgoWTEN3G+LWAfu/nBnrNFKodWh+nTUPMfTdDSRHhClpYD6VbmMcdJLljDD1Xaj2YKsH0YovZzeja4x76KSLKxSup0Fv3roAJHlM1Igr1rFdqTeQJfdCOFBHuYpt5b6M6irLUcx485GWpazdqFJa6RqJTzOs20f7G19dIv9f+31EJMoYTdNNw2e6+UzQYHvBqg2vvMmeZTOTxnZ9GfZVtXvn6duMeEkL0Lr5Dd1admekhqnLTs03iK1N1JgeSZ/kCKSJ6FzcxbdJMm6pm2MsR8+jg5tnP9wwGc9mLlBGcD2YB8y/uCWH+3625AfgJnUXTPOVWLZUAAAAASUVORK5CYII=">',
        product_2col : '<img alt="product 2col" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAgCAYAAABgrToAAAAACXBIWXMAAA57AAAOewEd6h3MAAACkUlEQVRYhe1YS2hTQRQ9Mwmh2FA/iK4U3OhGsaKgG7UvtbrRbSnYrgQVKoIIRcFFEUHoRgQFBbtQhCLoTpRSTaxuFBQURNwIIipIF7VgoWg715OX+FKTaTKTQHiFHMJw331z7pyZufN5UchpQYyhEXMkIyswCnFCcWaX0QhWw1McgcJWzOIq0ljBfp2DYA2UOY4A8zX5OfTDYDVe4Dr2YzP5J0J/YM7UorqNoNb78AejaNeX+HAKMBco7jl962pyH6GD5Xp2MEtx58k/hklzlr45p6ZdKjF4B1L4DWPG+LQFUxwPpXfgIL478aE3MMYnGp8hZppC86IX3Jr+t80sXiQTWMk3baX4WMXyKEeyHSLdEDXJhm5zmr9FdebYgcOYDu2HnM42dqmEjUigF6ITULKX/FfkXyH/V1RDGKEHM9FzUZc9B5P6Gsv+Cr8U+sTfdo7g6f/epSXLCt0FW92niEwFX0X8XeQPlr29y9QZKKfEfhW3BDaKlsBGsdRJwuUuP7wiSXGLiWxPPtSM1WvdB+OAqvtgFkNQ6oBXQFFvkTFDBb4e4Ybc6ceXJ8hgpNxtF6j0NpY9Xg1oSSyyd1o36mpQiilhKtx2gcIzV+GdVwMGX0q23OQIPvbiCz7Y3HaBC3hPgV/hh9K5avCa5UcvtuCnzW0XmEBfXTmIYg5qfbKuHERlDi7TVbwUxrEJKTWGQPZ48YZ5IHTpy2F7kr/kmlHexJ+5UP0EpnCIU/+meO9yxzCzctxcJHuW1/80Y9xjDCeBfkddgBss51EP8uLyEOzmKn/pSkuimciGF90BTPFjyxHNE5hDFyesLxTXy88DRzTnNjPBbxKlHtBKY62+xaPwjis19ttM7O+DrX+3GgPwFzYEwbtz/+59AAAAAElFTkSuQmCC">',
        product_3col : '<img alt="product 3col" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAgCAYAAABgrToAAAAACXBIWXMAAA57AAAOewEd6h3MAAADiklEQVRYhe2YS0hUURjH/+eM2qbIyqDAXgbRMmxhRUmOLwokF7ZoE7YRahOaUUaFbXKRELUoMCoIpQctiqAodW6CYIJFZosKsiRSoSEoQwVnzul/71wmZ+Y+BA1H8JM5995zf9/jfOd5FTCkRhqLRJpLRvyuSAmkk9g9655BA9kIoSD+HEIN6866sFvRgS3W/QNkkW1AJ444sh3YY9mO6eUykKYEP0mS4RqgluchVC+Vt0NjAgoDCCDPCqQEn+KcGZCWNQioYbIDEHInplQLMmUtqJQgnchnSvLJH0S7aua1nj7qqNNEttcpDPcMSkyhCPcZ3BikLGNQPazdhAjCCdxyZkOoIbLNfCogL1CGYZbSwWYeomhHWNUjU1ym3lPWLoVWY25h/JvFyWMwhGK2rBRK9SMgqqB0F53/YO3dFCsGqul9M9l31Kmg1e90+hBBvE7gzGzn4DTfR2nL9Bth4zdgXDVgP34n2ozFlRqggTV0diIOaqyC0GuJDvHJbql6y4y1kd1N9kC8sdrU1SsgxCCfJ232Mdludm8FgymMVSETUueS5xATn6kZGwtRdSM+fOy4nMZgDn/18ScRL6aJvEcvbXSQz1ferMY3lt0IyL28r4up21yyWcHux7TxjQWwDi4GOFtZDHC24jSLRzlDT/rofbBKzdkpfFiTsa7qCcsRT1YmzmBTnAKc5DLa7+N01Loq7iralw3b1xHy3mwkabGGc4AbuWa9gLeY6+Ah7s2V3DmueJJaHWd5lYt0Dde5Ok9WqHKWCb7Tfgy678XzLa5bncEuhmjxVNb6JQ8CF8lWkj3m7Ulf4178yDpPCh46vNkzZPum1ziNwTCi+pK3HXuSRPCGBwk/9qN910W7XzzZCAaTqxZgF6eA5vFc3GYqzjH97125Rk64QtnMJpt/Kzl7r5N/5WpT4yjn6GpqTZBtIvvHCfWfxZrnY60N+DWmkatcVF1gT9TS4SmOtwYPOsLzeiuCPO5HVV8sWGfxDzDIc7DAT8xESvHLvtvBRvW4cma2spg5A9u45pbQviubgbmWTuxiu6vQpQ57cgrrmZ6gdfTXWOKGzW2AIZRzZ9lH79VWl3tJMVcA8GeobEhxk2Op0wmbuwA7+MUmRCuz8YwZvMVgOTxcsmhm2cye4scVZDE/tu64mZ2/ZeY51nEvX8aR+JWfU+Mp72e8zPwvKbc+pnxl8b9bsxPgL5cSNrB7raipAAAAAElFTkSuQmCC">'
    };

    // Товар и описание внизу
    Revolvapp.add('block', 'block.product-top', {
        mixins: ['block'],
        type: 'product-top',
        icon: icons.product_top,
        title: 'Product with text below',
        section: 'shop',
        build: function () {
            this.block = this.app.create('tag.block');
            let block = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK', padding: '10px 20px 0 20px'});
            let grid = this.app.create('tag.grid');
            let column = this.app.create('tag.column', {align: 'center'});
            let image = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%', width: '270px', height: '250px'});
            let text1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a>', style: 'margin: 15px 0 0 0'});
            let text2 = this.app.create('tag.text', {html: '%PRODUCT_DESC%', style: 'font-size: 13px'});
            let text3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let text4 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            column.add(image);
            column.add(text1);
            column.add(text2);
            column.add(text3);
            column.add(text4);
            grid.add(column);
            block.add(grid);

            this.block.add(block);
        }
    });

    // Товар и описание справа
    Revolvapp.add('block', 'block.product-left', {
        mixins: ['block'],
        type: 'product-left',
        icon: icons.product_left,
        title: 'Product with text on the right',
        section: 'shop',
        build: function () {
            this.block = this.app.create('tag.block');

            let block = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK', padding: '10px 20px 0 20px'});
            let grid = this.app.create('tag.grid');
            let column1 = this.app.create('tag.column', {width: '290px'});
            let column2 = this.app.create('tag.column');
            let image = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%', width: '270px', height: '250px'});
            let text1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a>', style: 'margin: 5px 0 0 0'});
            let text2 = this.app.create('tag.text', {html: '%PRODUCT_DESC%', style: 'font-size: 13px'});
            let text3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let text4 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            column1.add(image);
            column2.add(text1);
            column2.add(text2);
            column2.add(text3);
            column2.add(text4);
            grid.add(column1);
            grid.add(column2);
            block.add(grid);

            this.block.add(block);
        }
    });

    // Товар и описание слева
    Revolvapp.add('block', 'block.product-right', {
        mixins: ['block'],
        type: 'product-right',
        icon: icons.product_right,
        title: 'Product with text on the left',
        section: 'shop',
        build: function () {
            this.block = this.app.create('tag.block');

            let block = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK', padding: '10px 20px 0 20px'});
            let grid = this.app.create('tag.grid');
            let column1 = this.app.create('tag.column', {align: 'left'});
            let column2 = this.app.create('tag.column', {align: 'right', width: '290px'});
            let image = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%', width: '270px', height: '250px'});
            let text1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a>', style: 'margin: 5px 0 0 0'});
            let text2 = this.app.create('tag.text', {html: '%PRODUCT_DESC%', style: 'font-size: 13px'});
            let text3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let text4 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            column1.add(text1);
            column1.add(text2);
            column1.add(text3);
            column1.add(text4);
            column2.add(image);
            grid.add(column1);
            grid.add(column2);
            block.add(grid);

            this.block.add(block);
        }
    });

    // Товары в две колонки
    Revolvapp.add('block', 'block.product-2columns', {
        mixins: ['block'],
        type: 'product-2columns',
        icon: icons.product_2col,
        title: 'Products in two columns',
        section: 'shop',
        build: function () {
            this.block = this.app.create('tag.block');

            let block = this.app.create('tag.block', {style: 'padding: 10px 20px 0 20px'});
            let block1 = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK'});
            let block2 = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK'});
            let grid_main = this.app.create('tag.grid');
            let grid1 = this.app.create('tag.grid');
            let grid2 = this.app.create('tag.grid');
            let column1 = this.app.create('tag.column');
            let column2 = this.app.create('tag.column');
            let col1 = this.app.create('tag.column', {style: 'align: center; width: 50%; padding: 5px'});
            let col2 = this.app.create('tag.column', {style: 'align: center; width: 50%; padding: 5px'});
            let image1 = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%'});
            let image2 = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%'});
            let c1txt1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a><br>', style: 'margin: 15px 0 0 0'});
            let c1txt2 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let c1txt3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});
            let c2txt1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a><br>', style: 'margin: 15px 0 0 0'});
            let c2txt2 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let c2txt3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            // table 1
            column1.add(image1);
            column1.add(c1txt1);
            column1.add(c1txt2);
            column1.add(c1txt3);
            grid1.add(column1);

            // table 2
            column2.add(image2);
            column2.add(c2txt1);
            column2.add(c2txt2);
            column2.add(c2txt3);
            grid2.add(column2);

            // main table
            block1.add(grid1);
            block2.add(grid2);
            col1.add(block1);
            col2.add(block2);
            grid_main.add(col1);
            grid_main.add(col2);
            block.add(grid_main);

            this.block.add(block);
        }
    });

    // Товары в три колонки
    Revolvapp.add('block', 'block.product-3columns', {
        mixins: ['block'],
        type: 'product-3columns',
        icon: icons.product_3col,
        title: 'Products in three columns',
        section: 'shop',
        build: function () {
            this.block = this.app.create('tag.block');

            let block = this.app.create('tag.block', {style: 'padding: 10px 20px 0 20px'});
            let block1 = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK'});
            let block2 = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK'});
            let block3 = this.app.create('tag.block', {class: 'SHOP_SCRIPT_PRODUCT_BLOCK'});
            let grid_main = this.app.create('tag.grid');
            let grid1 = this.app.create('tag.grid');
            let grid2 = this.app.create('tag.grid');
            let grid3 = this.app.create('tag.grid');
            let column1 = this.app.create('tag.column');
            let column2 = this.app.create('tag.column');
            let column3 = this.app.create('tag.column');
            let col1 = this.app.create('tag.column', {style: 'align: center; width: 33%; padding: 5px'});
            let col2 = this.app.create('tag.column', {style: 'align: center; width: 33%; padding: 5px'});
            let col3 = this.app.create('tag.column', {style: 'align: center; width: 33%; padding: 5px'});
            let image1 = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%'});
            let image2 = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%'});
            let image3 = this.app.create('tag.image', {src: '/wa-apps/mailer/img/placeholder.svg?%PRODUCT_IMAGE_URL%'});

            // let text1 = this.app.create('tag.text');
            let c1txt1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a><br>', style: 'margin: 15px 0 0 0'});
            let c1txt2 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let c1txt3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            // let text2 = this.app.create('tag.text');
            let c2txt1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a><br>', style: 'margin: 15px 0 0 0'});
            let c2txt2 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let c2txt3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            // let text3 = this.app.create('tag.text');
            let c3txt1 = this.app.create('tag.text', {html: '<a href="%PRODUCT_URL%">%PRODUCT_NAME%</a><br>', style: 'margin: 15px 0 0 0'});
            let c3txt2 = this.app.create('tag.text', {html: '%PRODUCT_PRICE%', style: 'color: #E54560; font-weight: bold; padding: 0 10px 0 0'});
            let c3txt3 = this.app.create('tag.text', {html: '%PRODUCT_PRICE_OLD%', style: 'text-decoration: line-through; font-size: 14px; color: #AAA'});

            // table 1
            column1.add(image1);
            column1.add(c1txt1);
            column1.add(c1txt2);
            column1.add(c1txt3);
            grid1.add(column1);

            // table 2
            column2.add(image2);
            column2.add(c2txt1);
            column2.add(c2txt2);
            column2.add(c2txt3);
            grid2.add(column2);

            // table 3
            column3.add(image3);
            column3.add(c3txt1);
            column3.add(c3txt2);
            column3.add(c3txt3);
            grid3.add(column3);

            // main table
            block1.add(grid1);
            block2.add(grid2);
            block3.add(grid3);
            col1.add(block1);
            col2.add(block2);
            col3.add(block3);
            grid_main.add(col1);
            grid_main.add(col2);
            grid_main.add(col3);

            block.add(grid_main);

            this.block.add(block);
        }
    });

    // https://imperavi.com/revolvapp/docs/create-a-plugin/
    Revolvapp.add('plugin', 'products', {
        translations: {
            en: {
                "products": {
                    "button_title": "Store",
                    "description": "These blocks will be filled with products details from the Store app."
                }
            }
        },
        init: function () {},
        start: function () {
            this.app.toolbar.add('add_block', {
                title: this.lang.get('products.button_title'),
                icon: '<i class="rex-icon-add"></i>',
                command: 'products.popup',
                position: {after: 'add'},
                components: ['main', 'heading', 'block', 'text', 'link'],
                observer: 'products.obs',
                background: '#00bf02'
            });
        },
        obs: function (button, name) {
            let hash = window.location.hash.split('/');
            let shop = document.getElementById('wa-app-shop');
            if (
                shop && shop.dataset.app === 'shop'
                && hash[1] && (hash[1] === 'templates' || hash[1] === 'template')
            ) {
                return button;
            }

            return false;
        },
        popup: function (button, name, e) {
            let params = {
                width: 600,
                builder: 'products.build_add_items',
            };
            this.app.popup.create('product_block_popup', params);
            this.app.popup.open({button: button});
        },
        build_add_items: function (stack) {
            let that = this;
            let types = ['product-top', 'product-left', 'product-right', 'product-2columns', 'product-3columns'];
            let $section = this.dom('<div>').addClass(this.prefix +'-popup-section');
            let $sectionBox = this.dom('<div>').addClass(this.prefix +'-popup-section-box');

            $section.html(this.lang.get('products.description'));
            for (let key in this.app._store) {
                if (this.app._store[key].type === 'block') {
                    let prod_block = this.app._store[key].proto.prototype;
                    if (types.indexOf(prod_block.type) > -1) {
                        let $item = this.dom('<span>').addClass(this.prefix +'-popup-section-item');
                        let title = that.lang.get('blocks.'+ prod_block.type) || prod_block.title;
                        $item.attr('data-type', prod_block.type);
                        $item.html(prod_block.icon);
                        $item.append(this.dom('<span>').html(title));
                        $sectionBox.append($item);

                        $item.on('click.revolvapp', function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            let $elms;
                            let $first;
                            let $blockSource;
                            let newInstance;
                            let $item = that.dom(e.target).closest('.'+ that.prefix +'-popup-section-item');
                            let type = $item.attr('data-type');
                            let instance = that.app.component.get();
                            let $source = instance.getSource();
                            let $element = instance.getElement();

                            newInstance = that.app.create('block.'+ type);
                            $blockSource = newInstance.getSource();
                            $elms = $blockSource.children();
                            $elms.nodes.reverse();
                            $elms.each(function ($node) {
                                let node = $node.get();
                                let tag = node.tagName.toLowerCase();
                                let newEl = that.app.create('tag.' + tag.replace('re-', ''), $node);
                                newEl.renderNodes();

                                let $newEl = newEl.getElement();
                                $element.after($newEl);
                                $source.after(newEl.getSource());

                                $first = $newEl;
                            }.bind(that));

                            that.app.popup.close();
                            that.app.editor.rebuild();
                            that.app.element.scrollTo($first);

                            // event
                            that.app.broadcast('component.add');
                        });
                    }
                }
            }
            if ($sectionBox.html() !== '') {
                stack.$body.append($section);
                stack.$body.append($sectionBox);
            }
        }
    });
})(Revolvapp);
