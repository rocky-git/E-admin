<template>
    <span v-if="displayType=='image'">
      <div v-for="(file,index) in files" :key="index" class="imgContainer" :style="{height: styleHeight,width:styleWidth}">
        <el-image
                class="image"
                fit="contain"
                :src="file"
                :preview-src-list="files"
                :style="{height: styleHeight,width:styleWidth}"
                @mouseover="showImgTool(index)"
                @mouseout="showImgToolIndex = -1"
        >
          <template #error>
            <div
                    class="imageslot"
                    :style="{width:styleWidth,height: styleHeight,backgroundColor:'#f5f7fa'}"
                    @mouseover="showImgTool(index)"
                    @mouseout="showImgToolIndex = -1"
            >
            <span>加载失败</span>
          </div>
          </template>

        </el-image>
        <span v-show="showImgToolIndex == index" class="el-upload-list__item-actions" @mouseout="showImgToolIndex = -1" @mouseover="showImgTool(index)">
          <span v-if="multiple" @click="imgLeft(index)"><i class="el-icon-caret-left" /></span>
          <span @click="fileDelete(index)"><i class="el-icon-delete" /></span>
          <span v-if="multiple" @click="imgRight(index)"><i class="el-icon-caret-right" /></span>
        </span>
      </div>
    </span>
    <span v-if="displayType=='image'" v-show="showUploadBtn" ref="btn"  @click="handelBrowse">
      <slot>
        <label class="uploader-btn" :style="{height: styleHeight,width:styleHeight}">
          <el-progress v-show="progressShow" class="progess" type="circle" :width="height" :percentage="percentage" />
          <i v-show="!progressShow" class="el-icon-plus progess" />
        </label>
      </slot>
    </span>
    <span v-if="displayType=='file'" class="fileList">
      <div v-for="(file,index) in files" :key="index" style="margin-bottom: 10px;" v-if="!foreverShow">
        <div class="el-upload-list__item is-success" :style="{width:styleWidth}" @mouseover="showImgTool(index)" @mouseout="showImgToolIndex = -1">
          <a class="el-upload-list__item-name" target="_blank" :href="file">
            <div v-if="showImgToolIndex == index">
              <span style="display: flex;align-items: center;">
                <i v-show="showImgToolIndex == index" class="el-icon-download" />
                <el-image v-show="showImgToolIndex != index" :src="fileIcon(file)" style="width: 32px;height: 32px;">
                  <template #error>
                  <div style="display: flex; align-items: center; top: 3px; position: absolute; left: 10px;"> <i class="el-icon-document" /></div>
                  </template>
                </el-image>
                &nbsp;&nbsp;{{ lastName(file) }}
              </span>
            </div>
            <div v-else>
              <span style="display: flex;align-items: center;top: 3px;position: absolute;left:5px">
                <i v-show="showImgToolIndex == index" class="el-icon-download" />
                <el-image v-show="showImgToolIndex != index" :src="fileIcon(file)" style="width: 32px;height: 32px;">
                  <template #error>
                     <div style="display: flex; align-items: center; top: 3px; position: absolute; left: 10px;"> <i class="el-icon-document" /></div>
                  </template>
                </el-image>
                &nbsp;&nbsp;{{ lastName(file) }}
              </span>
            </div>

          </a>
          <label class="el-upload-list__item-status-label"><i class="el-icon-upload-success el-icon-check" style="color: #ffffff" /></label>
          <i class="el-icon-close" @click="fileDelete(index)" /><i class="el-icon-close-tip" />
        </div>
      </div>
      <span @click="handelBrowse" v-if="displayType == 'file'" v-show="showUploadBtn || foreverShow" ref="btn" >
        <slot>
          <el-progress v-show="progressShow" style="margin: 13px 0px" :text-inside="true" :stroke-width="15" :percentage="percentage" />
          <label class="fileButton" >
          <template v-if="drag">
            <i class="el-icon-upload" />
            <div class="el-upload__text">将文件拖到此处，或<em>点击上传</em></div>
          </template>
          <template v-else>
            <i class="el-icon-upload" /> 上传文件
          </template>
          </label>
        </slot>
      </span>
    </span>
    <el-dialog title="资源库" v-model="dialogVisible" :append-to-body="true" width="70%" destroy-on-close>
      <keep-alive>
        <render :data="finder" :accept="accept" :multiple="multiple" display="menu" :height="finderHeight" v-model:selection="selection"></render>
      </keep-alive>
      <template #footer>
        <div :class="multiple && selection.length > 0 ? 'footer':''">
          <div v-if="multiple && selection.length > 0">已选中: {{selection.length}}</div>
          <div>
            <el-button type="primary" @click="submit">确认</el-button>
            <el-button @click="dialogVisible = false">取消</el-button>
          </div>
        </div>
      </template>
    </el-dialog>
</template>
<script>
import Uploader from 'simple-uploader.js'
import OSS from 'ali-oss'
import md5 from 'js-md5'
import * as qiniu from 'qiniu-js'
import {fileIcon, lastName, link, refresh} from '@/utils'
import {defineComponent, reactive, watch, nextTick, toRefs, ref,getCurrentInstance} from "vue";
import {ElMessage, ElNotification} from 'element-plus'
import {action} from "@/store";
import router from "@/router";
function noop() {}
export default defineComponent({
  name: 'EadminUpload',
  props: {
    modelValue: [String, Array],
    finder: {
      type: [Object, Boolean],
      default: false
    },
    token: {
      type: String,
      default: ''
    },
    width: {
      type: [String, Number],
      default: 'auto'
    },
    height: {
      type: [String, Number],
      default: 'auto'
    },
    url: {
      type: String,
      default: '/'
    },
    params: {
      type: Object,
      default: {}
    },
    upType: {
      type: String,
      default: 'local'
    },
    accessKey: {
      type: String,
      default: ''
    },
    secretKey: {
      type: String,
      default: ''
    },
    bucket: {
      type: String,
      default: ''
    },
    region: {
      type: String,
      default: ''
    },
    domain: {
      type: String,
      default: ''
    },
    uploadToken: {
      type: String,
      default: ''
    },
    accept: {
      type: String,
      default: '*'
    },
    saveDir: {
      type: String,
      default: ''
    },
    multiple: {
      type: Boolean,
      default: false
    },
    displayType: {
      type: String,
      default: 'file'
    },
    isUniqidmd5: {
      type: Boolean,
      default: false
    },
    foreverShow:Boolean,
    onProgress: {
      type: Function,
      default: noop
    },
    drag: {
      type: Boolean,
      default: false
    },
    chunk: {
      type: Boolean,
      default: true
    },
  },
  emits: ['success','update:modelValue'],
  setup(props,ctx){
    const state = reactive({
      styleWidth: '',
      styleHeight: '',
      selection:props.modelValue,
      files: [],
      dialogVisible: false,
      // 进度条显示
      progressShow: false,
      // 进度条百分比
      percentage: 0,
      // 图片工具栏index,判断显示隐藏
      showImgToolIndex: -1,
      // 显示隐藏上传按钮
      showUploadBtn: true,
      oss: null,
      finderHeight:(window.innerHeight / 2) + 'px'
    })
    if(!Array.isArray(state.selection)){
      state.selection = [state.selection]
    }
    const instance = getCurrentInstance()
    watch(()=>props.modelValue,val=>{
      if (typeof val === 'string') {
        state.files = val.split(',')
        state.files = state.files.filter(function(s) {
          return s && s.trim()
        })
      } else if (typeof val === 'object' && val instanceof Array) {
        state.files = val
      }
    })
    watch(()=>state.files,val=>{
      if (!props.multiple && val.length === 1) {
        state.showUploadBtn = false
      } else if (val.length === 0) {
        state.showUploadBtn = true
      }
      state.selection = JSON.parse(JSON.stringify(val))
      if(instance.parent && instance.parent.type.name === 'ElFormItem'){
        instance.parent.provides.elFormItem.formItemMitt?.emit('el.form.change', [val.join(',')])
      }
      ctx.emit('update:modelValue', val.join(','))
    },{deep:true})
    if (props.width != 'auto') {
      state.styleWidth = props.width + 'px'
    } else {
      state.styleWidth = '100%'
    }
    const btn = ref('')

    if (props.height != 'auto') {
      state.styleHeight = props.height + 'px'
    } else {
      state.styleHeight = '100%'
    }

    if (typeof props.modelValue === 'string') {
      state.files = props.modelValue.split(',')
      state.files = state.files.filter(function(s) {
        return s && s.trim()
      })
    } else if (typeof props.modelValue === 'object' && props.modelValue instanceof Array) {
      state.files = props.modelValue
    }
    let oss = null
    if (props.upType == 'oss') {
      oss = new OSS({
        accessKeyId: props.accessKey,
        accessKeySecret: props.secretKey,
        bucket: props.bucket,
        region: props.region
      })
    }
    const uploader = new Uploader({
      target: props.url,
      query: Object.assign(props.params,{
        saveDir: props.saveDir,
        isUniqidmd5: props.isUniqidmd5,
        upType: props.upType
      }),
      testChunks: props.chunk,
      chunkSize: props.chunk ? 1 * 1024 * 1024 : 500 * 1024 * 1024,
      headers: {
        Authorization: props.token
      }
    })
    watch(()=>props.saveDir,value=>{
      uploader.opts.query.saveDir = value
    })
    nextTick(() => {
      if(!props.finder){
        uploader.assignDrop(btn.value)
        uploader.assignBrowse(btn.value, false, !props.multiple, {
          accept: props.accept
        })
      }
    })
    uploader.on('fileAdded', function(file, event) {
      if (checkExt(file)) {
        if (props.upType == 'oss') {
          ossMultipartUpload(file)
        } else if (props.upType == 'qiniu') {
          qiniuMultipartUpload(file)
        }
      } else {
        uploader.cancel()
        ElMessage({
          type: 'error',
          message: '不支持的上传类型格式'
        })
        return false
      }
    })
    // 开始上传
    uploader.on('uploadStart', function() {

    })
    // 文件已经加入到上传列表中，一般用来开始整个的上传。
    uploader.on('filesSubmitted', function(files, fileList) {
      if (props.upType != 'oss' && props.upType != 'qiniu') {
        uploader.upload()
      }
      if (files.length > 0) {
        state.progressShow = true
      }
    })
    // 单个文件上传成功
    uploader.on('fileSuccess', function(rootFile, file, message) {

      try {
        const res = JSON.parse(message)
        if (res.code == 200) {
          uploader.removeFile(file)
          state.progressShow = false
          if (!props.multiple) {
            state.files = []
          }
          ctx.emit('success')
          state.files.push(res.data)
        }else if (res.code == 80020) {
          ElMessage({
            showClose: true,
            dangerouslyUseHTMLString: true,
            message: res.message,
            type: res.type
          })
          if (res.url) {
            if (res.url == 'back') {
              if (res.refresh) {
                action.refresh(true)
              }
              router.back()
            } else {
              link(res.url)
              return
            }
          }
          if (res.refresh) {
            refresh()
          }
          if (res.type == 'success') {
            res.code = 200
            return res
          }
        } else if (res.code == 80021) {
          ElNotification({
            showClose: true,
            dangerouslyUseHTMLString: true,
            title: res.title,
            message: res.message,
            type: res.type,
            duration: 1500
          })
          if (res.url) {
            if (res.url == 'back') {
              if (res.refresh) {
                action.refresh(true)
              }
              router.back()
              return
            } else {
              link(res.url)
              return
            }
          }
          if (res.refresh) {
            refresh()
          }
          if (res.type == 'success') {
            res.code = 200
            return res
          }
        }
      } catch (e) {
        uploader.removeFile(file)
        state.progressShow = false
        ElMessage({
          type: 'error',
          message: '上传失败:未知错误'
        })
      }
    })
    uploader.on('fileProgress', function(rootFile, file, chunk) {
      state.progressShow = true
      state.percentage = parseInt(uploader.progress() * 100)
      props.onProgress(state.percentage)
    })
    // 根下的单个文件（文件夹）上传完成
    uploader.on('fileComplete', function(rootFile) {
      // console.log(rootFile)
    })
    // 某个文件上传失败了
    uploader.on('fileError', function(rootFile, file, message) {
      uploader.removeFile(file)
      state.progressShow = false
      try {
        const res = JSON.parse(message)
        ElMessage({
          type: 'error',
          message: res.message
        })
      } catch (e) {
        ElMessage({
          type: 'error',
          message: '上传失败:未知错误'
        })
      }
    })


    function uniqidMd5() {
      const rand = ('0000' + (Math.random() * Math.pow(36, 4) << 0).toString(36)).slice(-4)
      return md5(rand)
    }
    // 图片工具栏显示
    function showImgTool(index) {
      state.showImgToolIndex = index
    }
    // 图片左移动
    function imgLeft(index) {
      if (index > 0) {
        swapArray(state.files, index - 1, index)
      }
    }
    // 图片右移动
    function imgRight(index) {
      if (index < state.files.length - 1) {
        swapArray(state.files, index, index + 1)
      }
    }
    function swapArray(arr, index1, index2) {
      arr[index1] = arr.splice(index2, 1, arr[index1])[0]
      return arr
    }
    // 图片移除
    function fileDelete(key) {
      state.files.splice(key, 1)
    }
    // 判断是否支持上传个类型格式
    function checkExt(file) {
      if (props.accept == '*') {
        return true
      } else if (props.displayType == 'image' && file.fileType.indexOf('image') != -1) {
        return true
      } else {
        const ext = props.accept.split(',')
        if (ext.indexOf('.' + file.getExtension()) == -1) {
          return false
        } else {
          return true
        }
      }
    }
    // 七牛云上传
    async function qiniuMultipartUpload(file) {
      let filename = ''
      if (props.isUniqidmd5) {
        filename = props.saveDir + uniqidMd5() + '.' + file.getExtension()
      } else {
        filename = props.saveDir + file.name
      }
      state.progressShow = true
      var observable = qiniu.upload(file.file, filename, props.uploadToken, {
        fname: filename,
        params: {}
      })
      await observable.subscribe({
        next(res) {
          state.progressShow = true
          state.percentage = parseInt(res.total.percent)
          props.onProgress(state.percentage)
        },
        error(err) {
          state.progressShow = false
          ElMessage({
            type: 'error',
            message: err.message
          })
        },
        complete(res) {
          uploader.removeFile(file)
          state.progressShow = false
          const url = `${props.domain}/${filename}`
          if (!props.multiple) {
            state.files = []
          }
          state.files.push(url)
          ctx.emit('success')
        }
      })
    }
    // 阿里云开始分片上传。
    async function ossMultipartUpload(file) {
      let filename = ''
      if (props.isUniqidmd5) {
        filename = props.saveDir + uniqidMd5() + '.' + file.getExtension()
      } else {
        filename = props.saveDir + file.name
      }
      state.progressShow = true
      // object-name可以自定义为文件名（例如file.txt）或目录（例如abc/test/file.txt）的形式，实现将文件上传至当前Bucket或Bucket下的指定目录。
      await oss.multipartUpload(filename, file.file, {
        progress: function(percentage) {
          state.progressShow = true
          state.percentage = parseInt(percentage * 100)
          props.onProgress(state.percentage)
        }
      }).then(result => {
        // 生成文件下载地址
        uploader.removeFile(file)
        state.progressShow = false
        const url = `${props.domain}/${filename}`
        if (!props.multiple) {
          state.files = []
        }
        state.files.push(url)
        ctx.emit('success')
      }).catch(err => {
        state.progressShow = false
        ElMessage({
          type: 'error',
          message: err
        })
      })
    }
    function handelBrowse() {
      if(props.finder){
        state.dialogVisible = true
      }
      state.percentage = 0
    }
    function submit() {
      state.dialogVisible = false
      state.files = state.selection

    }
    return {
      btn,
      submit,
      lastName,
      fileIcon,
      handelBrowse,
      fileDelete,
      imgRight,
      imgLeft,
      showImgTool,
      ...toRefs(state)
    }
  },

})
</script>

<style scoped>

  .vue-cropper-content {
    width: 100%;
    height: 500px;
    padding-bottom: 20px;
    text-align: center;
    display: flex;
    justify-content: space-between;
  }
  .fileButton {
    background: #fff;
    border: 1px solid #dcdfe6;
    color: #606266;
    display: inline-block;
    white-space: nowrap;
    cursor: pointer;
    -webkit-appearance: none;
    text-align: center;
    box-sizing: border-box;
    outline: none;
    margin: 0;
    transition: .1s;
    font-weight: 500;
    -moz-user-select: none;
    -webkit-user-select: none;
    -ms-user-select: none;
    padding: 0px 20px;
    font-size: 14px;
    border-radius: 4px;
  }

  .el-upload-list__item-status-label i {
    font-size: 12px;
    margin-top: 12px;
    position: absolute;
    top: 0;
    right: 17px;
    transform: rotate(-45deg);
  }
  .el-upload-list__item {
    display:flex;justify-content:space-between;
    overflow: hidden;
    z-index: 0;
    background-color: #fff;
    border: 1px solid #c0ccda;
    border-radius: 6px;
    box-sizing: border-box;
    padding: 7px 10px 0px 10px;
    height: 40px;
    margin-top: 0px;
    margin-bottom: 10px;
  }
  .el-upload-list__item-status-label {
    position: absolute;
    right: -17px;
    top: -7px;
    width: 46px;
    height: 26px;
    background: #13ce66;
    transform: rotate(45deg);
    box-shadow: 0 1px 1px #ccc;
  }
  .imageslot{
    width: 100%;
    height: 100%;
    background-color: #d3dce6;
    position: relative;
    border-radius: 5px;
    text-align: center;
  }
  .imageslot i{
    font-size: 30px;
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  }
  .imageslot span{
    font-size: 12px;
    width: 100%;
    text-align: center;
    position: absolute; left: 50%; transform: translate(-50%,0);
  }
  .img_delete{
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  }
  .el-upload-list__item-actions {
    position: absolute;
    width: 100%;
    height: 35px;
    cursor: pointer;
    left: 0;
    top: 0;
    text-align: center;
    color: #fff;
    font-size: 20px;
    background-color: rgba(0,0,0,.5);
    border-radius: 5%;
    transition: opacity .3s;
  }
  .imgContainer{
    margin-right: 10px;
    position: relative;;
    display: inline-block;
  }
  .image{
    border-radius: 5%;
  }
  .progess{
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  }
  .uploader-btn {
    background-color: #fbfdff;
    border: 1px dashed #c0ccda;
    border-radius: 5px;
    box-sizing: border-box;
    cursor: pointer;
    vertical-align: top;
    display: inline-block;
    text-align: center;
    position: relative;
  }
  .uploader-btn  i {
    font-size: 28px;
    color: #8c939d;
  }
  .uploader-btn:hover {
    border: 1px dashed #2d8cf0;
  }
  .footer{
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
</style>
