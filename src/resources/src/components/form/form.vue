<template>
    <el-main class='eadmin-form' :style="$attrs.style">
        <el-form ref="eadminForm" :label-position="labelPosition" v-bind="$attrs" @submit.native.prevent>
            <slot></slot>
            <render :data="stepResult"></render>
            <el-form-item v-if="!action.hide" v-bind="action.attr">
                <slot name="leftAction"></slot>
                <render v-if="action.submit" :loading="loading" :data="action.submit" :disabled="disabled"></render>
                <render v-if="action.reset" :data="action.reset" @click="resetForm"></render>
                <render v-if="action.cancel" :data="action.cancel" @click="cancelForm"></render>
                <slot name="rightAction"></slot>
            </el-form-item>
            <slot name="footer"></slot>
        </el-form>
    </el-main>
</template>

<script>
    import {defineComponent, inject,nextTick,ref,watch,computed,isReactive} from 'vue'
    import manyItem from "./manyItem.vue"
    import { store } from '@/store'
    import { useHttp } from '@/hooks'
    import { forEach ,debounce} from '@/utils'
    import request from '@/utils/axios'
    export default defineComponent({
        components:{
            manyItem
        },
        inheritAttrs: false,
        name: "EadminForm",
        props:{
            action:Object,
            //提交url
            setAction: String,
            setActionMethod:{
                type:String,
                default:'post'
            },
            reset:Boolean,
            submit:Boolean,
            validate:Boolean,
            step:Number,
            watch:{
                type:Array,
                default:[],
            },
            exceptField:{
                type:Array,
                default:[],
            },
            proxyData:Object,
        },
        emits: ['success','gridRefresh','PopupRefresh','update:submit','update:reset','update:validate','update:step','update:eadminForm'],
        setup(props,ctx){
            const eadminForm = ref(null)
            const stepResult = ref(null)
            const disabled = ref(false)
            const {loading,http} = useHttp()
            const state = inject(store)
            const proxyData = props.proxyData
            const validateStatus = ref(false)
            const initModel = JSON.parse(JSON.stringify(ctx.attrs.model))
            //提交
            watch(()=>props.submit,val=>{
                if(val){
                    sumbitForm()
                }
            })
            //重置
            watch(()=>props.reset,val=>{
                if(val){
                    resetForm()
                    stepResult.value = null
                }
            })
            const debounceWatch = debounce((args)=>{
                const length = watchData.length
                watchData.push({
                    field:args[0],
                    newValue:args[1],
                    oldValue:args[2],
                })
                if(length === 0){
                    watchListen()
                }
            }, 300)
            //watch监听变化
            const watchData = []
            props.watch.forEach(field=>{
                watchData.push({
                    field:field,
                    newValue:ctx.attrs.model[field],
                    oldValue:ctx.attrs.model[field],
                })
                watch(()=>ctx.attrs.model[field],(newValue,oldValue)=>{
                    debounceWatch([field,newValue,oldValue],field)
                },{deep:true})
            })
            watchListen()
            //监听watch变化数据队列执行
            async function watchListen(){
                const copyData = JSON.parse(JSON.stringify(watchData))
                const data = copyData.shift()
                disabled.value = true
                if(data){
                    await watchAjax(data.field,data.newValue,data.oldValue)
                    watchData.shift()
                    watchListen()
                }else{
                    disabled.value = false
                }
            }
            //watch ajax请求
            function watchAjax(field,newValue,oldValue){
                return new Promise((resolve,reject) => {
                    request({
                        url: props.setAction,
                        method: props.setActionMethod,
                        data: {
                            field:field,
                            newValue:newValue,
                            oldValue:oldValue,
                            form:ctx.attrs.model,
                            eadmin_form_watch:true,
                            eadmin_class:ctx.attrs.model['eadmin_class'],
                            eadmin_function:ctx.attrs.model['eadmin_function']
                        }
                    }).then(res=>{
                        res.data.showField.forEach(field=>{
                            proxyData[field] = 1
                        })
                        res.data.hideField.forEach(field=>{
                            proxyData[field] = 0
                        })
                        let formData = res.data.form
                        for(let f in formData){
                            if(f == field && JSON.stringify(formData[f]) !== JSON.stringify(newValue)){
                                if(isReactive(ctx.attrs.model[f])){
                                    Object.assign(ctx.attrs.model[f],formData[f])
                                }else{
                                    ctx.attrs.model[f] = formData[f]
                                }
                            }else if(f != field && ctx.attrs.model[f] != formData[f]){
                                if(isReactive(ctx.attrs.model[f])){
                                    Object.assign(ctx.attrs.model[f],formData[f])
                                }else{
                                    ctx.attrs.model[f] = formData[f]
                                }
                            }
                        }
                        resolve(res)
                    }).catch((res)=>{
                        resolve(res)
                    })
                })
            }
            //校验
            watch(()=>props.validate,val=>{
                if(val){
                    ctx.emit('update:validate',false)
                    sumbitForm(true)
                }
            })
            watch(validateStatus,val=>{
                if(val){
                    validateStatus.value = false
                    ctx.emit('update:step',++props.step)
                }
            })
            //提交
            function sumbitForm(validate=false) {
                ctx.emit('update:submit',false)
                let params = {}
                if(validate){
                    params.eadmin_validate = true
                }
                if(props.setAction){
                    clearValidator()
                    eadminForm.value.validate(bool=>{
                        const submitData = JSON.parse(JSON.stringify(ctx.attrs.model))
                        forEach(submitData,function (val,key) {
                            if(props.exceptField.indexOf(key) > -1){
                                delete submitData[key]
                            }else if(Array.isArray(val)){
                                forEach(val,function (many) {
                                    forEach(many,function (value,field) {
                                        if(props.exceptField.indexOf(field) > -1){
                                            delete many[field]
                                        }
                                    })
                                })
                            }
                        })
                        if(bool){
                            http({
                                url: props.setAction,
                                params:params,
                                method: props.setActionMethod,
                                data: submitData
                            }).then(res=>{
                                if(res.code === 422){
                                    for (let field in res.data){
                                        if(res.index){
                                            let fields = field.split('.')
                                            let name = fields.shift()
                                            let f = fields.shift()
                                            if(!proxyData[ctx.attrs.validator][name]){
                                                proxyData[ctx.attrs.validator][name] = []
                                            }
                                            if(!proxyData[ctx.attrs.validator][name][res.index]){
                                                proxyData[ctx.attrs.validator][name][res.index] = {}
                                            }
                                            proxyData[ctx.attrs.validator][name][res.index][f] = res.data[field]
                                        }else{
                                            const validatorField = field.replace('.','_')
                                            proxyData[ctx.attrs.validator][validatorField] = res.data[field]
                                        }
                                    }
                                    scrollIntoView()
                                }else  if(res.code === 412){
                                    validateStatus.value = true
                                }else{
                                    if(res.type == 'step'){
                                        stepResult.value = res.data
                                        ctx.emit('update:step',++props.step)
                                    }
                                    ctx.emit('success')
                                    ctx.emit('PopupRefresh')
                                    ctx.emit('gridRefresh')
                                }
                            })
                        }else{
                            scrollIntoView()
                            return false
                        }
                    })
                }else{
                    validateStatus.value = true
                    ctx.emit('update:submit',false)
                    ctx.emit('success')
                    ctx.emit('gridRefresh')
                }
            }
            //滚动到校验错误处
            function scrollIntoView() {
                nextTick(()=>{
                    let isError  = document.getElementsByClassName('is-error')
                    if(isError){
                        isError[0].scrollIntoView({
                            block: 'center',
                            behavior: 'smooth'
                        })
                    }
                })
            }
            //清除校验结果
            function clearValidator() {
                for (let field in proxyData[ctx.attrs.validator]){
                    let value = proxyData[ctx.attrs.validator][field]
                    if(Array.isArray(value)){
                        proxyData[ctx.attrs.validator][field] = []
                    }else{
                        proxyData[ctx.attrs.validator][field] = ''
                    }
                }
                eadminForm.value.clearValidate()

            }
            const labelPosition = computed(()=>{
                if(state.device === 'mobile'){
                    return 'top'
                }else{
                    return 'right'
                }
            })
            //重置
            function resetForm() {
                clearValidator()
                eadminForm.value.resetFields();
                Object.assign(ctx.attrs.model,initModel)
                ctx.emit('update:reset',false)
            }
            //取消
            function cancelForm(){
                ctx.emit('success')
            }
            return {
                stepResult,
                disabled,
                eadminForm,
                loading,
                resetForm,
                cancelForm,
                labelPosition,
            }
        }
    })
</script>

<style scoped>
.eadmin-dialog .el-form-item:last-child{
   margin-bottom: 0;
}
.eadmin-dialog .footer{
    position: absolute;
    bottom: 0;
    background: #ffffff;
    width: 100%;
    margin-bottom: 0;
    padding-bottom:10px;
}
.eadmin-form{
    background: rgb(255, 255, 255);
    border-radius: 4px;
    white-space:normal;
}
</style>
