$ ->
    # upload progress value
    uploadProgress = 0

    # interval for checkStatus
    interval = null

    # check status
    url = $('#fileupload').data 'check'

    getProgressBarWidth = ->
        width = uploadProgress
        width += 12 if $xhdpi.hasClass 'bounceInDown'
        width += 12 if $hdpi.hasClass 'bounceInDown'
        width += 12 if $mdpi.hasClass 'bounceInDown'
        width += 14 if $ldpi.hasClass 'bounceInDown'
        width

    checkStatus = ->
        $.getJSON url, (response) ->
            # toggle views
            $xhdpi.addClass 'bounceInDown' if response.xhdpi
            $hdpi.addClass 'bounceInDown' if response.hdpi
            $mdpi.addClass 'bounceInDown' if response.mdpi
            $ldpi.addClass 'bounceInDown' if response.ldpi

            # increment progress bar width
            $bar.width "#{getProgressBarWidth()}%"

            # if all conversions are done...
            if response.ldpi and response.mdpi and response.hdpi and response.xhdpi
                # clear interval
                clearInterval interval

                # stop and hide progress
                $progress.removeClass('active progress-striped').addClass 'fade'

    # prepare uploader
    $('#fileupload').fileupload
        add: (e, data) ->
            # check file is a zip
            for file in data.files
                return false unless file.type == 'application/zip'

            # start upload
            data.submit()

            # hide fileupload and show progress
            $button.addClass 'hide'
            $progress.removeClass 'hide'

            # file is uploaded, start checking status of resizes (very 5 seconds)
            interval = setInterval checkStatus, 5000

            # call for first time
            checkStatus()

        progressall: (e, data) ->
            # get progess percent
            uploadProgress = parseInt data.loaded / data.total * 100 / 2, 10

            # set bar width
            $bar.width "#{getProgressBarWidth()}%"

        error: ->
            # clear interval
            clearInterval interval

            # stop progress
            $progress.removeClass 'active progress-striped'
            $bar.removeClass('bar-success').addClass 'bar-danger'

            # show error
            alert 'error'

    # get upload button
    $button = $('#fileinput-button')

    # get progress
    $progress = $('#progress')

    # get bar
    $bar = $progress.find 'div'

    # get downloads
    $xhdpi = $('#xhdpi')
    $hdpi = $('#hdpi')
    $mdpi = $('#mdpi')
    $ldpi = $('#ldpi')
